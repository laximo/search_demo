<?php

namespace LaximoSearch\controllers;


use LaximoSearch\modules\User;

class userController extends Controller
{
    public function login()
    {
        $form = $this->input->formData();
        $user = (isset($form['user']) && is_array($form['user'])) ? $form['user'] : [];

        if (!empty($user['login']) && !empty($user['password'])) {
            if (User::loginToServices(trim($user['login']), $user['password'], $this->getConfig())) {
                if (!empty($user['json']) && $user['json']) {
                    $this->responseJson($user);
                }
            } elseif (!empty($user['json']) && $user['json']) {
                $this->responseJson(['error' => true, 'message' => $this->getLanguage()->t('AUTHORIZATION_FAILED')]);
            }
        }

        $this->redirectToUrl(isset($user['return']) ? $user['return'] : '/');
    }

    public function logout()
    {
        User::logout();

        if ($this->input->getString('format') === 'json') {
            $this->responseJson(['success' => true]);
        }

        $this->redirect('', '', []);
    }
}