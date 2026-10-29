<?php

namespace LaximoSearch\controllers;

use Exception;
use GuayaquilLib\objects\oem\AttributeObject;
use GuayaquilLib\objects\oem\ImageMapObject;
use GuayaquilLib\objects\oem\PartListObject;
use GuayaquilLib\objects\oem\PartObject;
use GuayaquilLib\objects\oem\QuickDetailListObject;
use GuayaquilLib\objects\oem\UnitObject;
use GuayaquilLib\objects\oem\VehicleObject;
use GuayaquilLib\Oem;
use GuayaquilLib\ServiceOem;
use Laximo\Search\responseObjects\UsOemParams;
use Laximo\Search\responseObjects\UsSearchDetail;
use Laximo\Search\responseObjects\UsSearchByOemsResponse;

class unitController extends Controller
{
    /**
     * @var array
     */
    public $details = [];

    /**
     * @var string|int
     */
    public $detailIds;

    /**
     * @var UsOemParams
     */
    public $vehicleParams;

    /**
     * @var string
     */
    public $oem = '';

    /**
     * @var int|string
     */
    public $autoId;

    /**
     * @var UnitObject
     */
    public $unit;

    /**
     * @var ImageMapObject
     */
    public $imagemap;

    /**
     * @var array
     */
    public $detailImageCodes = [];

    /**
     * @var array
     */
    public $oemServiceData = [];

    /**
     * @var string|null
     */
    public $highlightCode;

    /**
     * @var VehicleObject
     */
    public $vehicleInfo;

    public function show()
    {
        $autoId    = $this->input->getInt('autoId');
        $detailIds = $this->input->getInt('detailIds');
        $oem       = $this->input->getString('oem');

        $us = $this->getSearchService();

        $oems   = $us->resolveDetailIdsToOemsForAutoInfo($autoId, explode(',', $detailIds));
        $params = $us->getParamsForOemService($autoId)->data;

        $oemServiceData = [];
        $service        = $this->getOemService();
        $notSupported   = false;
        foreach ($oems->data as $originalDetail) {
            try {
                $oemServiceData[] = $service->findPartInVehicle($params->catalog, $params->ssd, $originalDetail->oem, $this->getOemLocale());
            } catch (Exception $ex) {
                if (strpos($ex->getMessage(), 'NOTSUPPORTED')) {
                    $notSupported = true;
                } else {
                    $this->renderError('500', $ex->getMessage());
                }
            }
        }

        if ($notSupported) {
            $this->render('unit', 'notSupported.twig');
        } else {
            $nodes = [];
            foreach ($oemServiceData as $webServiceData) {
                foreach ($webServiceData->getCategories() as $category) {
                    foreach ($category->getUnits() as $unit) {
                        $nodes[] = $unit;
                    }
                }
            }

            if (count($nodes) == 1) {
                /** @var $unit UnitObject */
                $unit = reset($nodes);

                $this->redirect('unit', 'unit', [
                    'detailIds' => $detailIds,
                    'unitid'    => $unit->getUnitId(),
                    'ssd'       => $unit->getSsd(),
                    'catalog'   => $params->catalog,
                    'vehicleId' => $params->vehicleId,
                    'oem'       => $oem,
                    'autoId'    => $autoId
                ]);
            }

            $this->pathway->addItem('Laximo.Search', $this->getBaseUrl());
            $this->pathway->addItem($this->getLanguage()->t('SEARCH_DEMO'), $this->createUrl('search', 'show'));
            $this->pathway->addItem($oem, '');

            $this->oemServiceData = $oemServiceData;
            $this->detailIds      = $detailIds;
            $this->vehicleParams  = $params;
            $this->autoId         = $autoId;
            $this->oem            = $oem;

            $this->render('unit', 'view.twig', true);
        }
    }

    /**
     * @param                   $name
     * @param AttributeObject[] $attrs
     * @return mixed|string
     */
    private function getAttr($name, array $attrs)
    {
        foreach ($attrs as $attr) {
            if ($attr->getName() === $name) {
                return $attr->getValue();
            }
        }

        return '';
    }

    /**
     * @param UsSearchDetail $item
     * @param PartObject     $original
     * @return \stdClass
     */
    private function buildDetailRow(UsSearchDetail $item, PartObject $original)
    {
        $row = new \stdClass();
        foreach (get_object_vars($item) as $property => $value) {
            $row->$property = $value;
        }
        $row->amount = $this->getAttr('amount', $original->getAttributes());
        $row->note   = $this->getAttr('note', $original->getAttributes());
        $row->code   = $original->getCodeOnImage();

        return $row;
    }

    public function unit()
    {
        $autoId    = $this->input->getInt('autoId');
        $oem       = $this->input->getString('oem');
        $unitId    = $this->input->getString('unitid');
        $ssd       = $this->input->getString('ssd');
        $catalog   = $this->input->getString('catalog');
        $vehicleId = $this->input->getString('vehicleId');

        $service = $this->getOemService();
        /** @var PartListObject $detailsList */
        /** @var UnitObject $unit */
        /** @var ImageMapObject $imageMap */
        /** @var VehicleObject $vehicleInfo */
        try {
            list($unit, $detailsList, $imageMap, $vehicleInfo) = $service->queryButch([
                Oem::getUnitInfo($catalog, $ssd, $unitId, $this->getOemLocale()),
                Oem::listPartsByUnit($catalog, $ssd, $unitId, $this->getOemLocale()),
                Oem::listImageMapByUnit($catalog, $ssd, $unitId),
                Oem::getVehicleInfo($catalog, $vehicleId, $ssd, $this->getOemLocale()),
            ]);
        } catch (Exception $ex) {
            $this->renderError('500', $ex->getMessage());
        }

        $oems                = [];
        $detailImageCodes    = [];
        $this->highlightCode = null;

        if (!empty($detailsList->getParts())) {
            foreach ($detailsList->getParts() as $detail) {
                if (!empty($detail->getOem())) {
                    $oems[] = $detail->getOem();
                }
            }
        }

        $us           = $this->getSearchService();
        $searchByOems = $us->searchByOems($autoId, $oems, $this->getLanguage()->getLocalization());
        $details      = [];

        foreach ($detailsList->getParts() as $original) {
            foreach ($searchByOems->data as $part) {
                if ($this->filterOem($part->oem) == $this->filterOem($original->getOem()) && is_countable($part->details) && count($part->details)) {
                    foreach ($part->details as $item) {
                        $detailImageCodes[$item->oem . $item->brand] = $original->getCodeOnImage();
                        $row = $this->buildDetailRow($item, $original);

                        $details[$original->getCodeOnImage()][$item->oem . $item->brand] = $row;

                        if ($item->oem == $oem) {
                            $this->highlightCode = 'i' . $original->getCodeOnImage();
                        }
                    }
                }
            }
        }

        $this->pathway->addItem('Laximo.Search', $this->getBaseUrl());
        $this->pathway->addItem($this->getLanguage()->t('SEARCH_DEMO'), $this->createUrl('search', 'show'));
        $this->pathway->addItem($unit->getName(), '');

        $this->unit             = $unit;
        $this->details          = $details;
        $this->oem              = $oem;
        $this->imagemap         = $imageMap;
        $this->detailImageCodes = $detailImageCodes;
        $this->vehicleInfo      = $vehicleInfo;

        $this->render('unit', 'unit.twig', true);
    }
}