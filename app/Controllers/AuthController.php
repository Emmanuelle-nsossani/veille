<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        $this->render('auth/login', ['firstRun' => !Auth::isSetUp($this->config)]);
    }

    public function login(): void
    {
        $this->requireCsrf();
        $password = (string) ($_POST['password'] ?? '');

        if (!Auth::isSetUp($this->config)) {
            if (mb_strlen($password) < 8 || $password !== ($_POST['password_confirm'] ?? '')) {
                $this->flash('error', 'Le mot de passe doit faire au moins 8 caractères et être identique dans les deux champs.');
                $this->redirect('/login');
            }
            Auth::setUp($this->config, $password);
            Auth::attempt($this->config, $password);
            $this->flash('success', 'Mot de passe créé. Pense à lancer une première récupération depuis la page Sources.');
            $this->redirect('/sources');
        }

        if (Auth::attempt($this->config, $password)) {
            $this->redirect('/');
        }
        usleep(500000); // ralentit les essais en série
        $this->flash('error', 'Mot de passe incorrect.');
        $this->redirect('/login');
    }

    public function logout(): void
    {
        $this->requireCsrf();
        Auth::logout();
        $this->redirect('/login');
    }
}
