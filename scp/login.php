<?php
/*********************************************************************
    login.php

    Handles staff authentication/logins

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require_once('../main.inc.php');
if(!defined('INCLUDE_DIR')) die('Fatal Error. Kwaheri!');

// Bootstrap gettext translations. Since no one is yet logged in, use the
// system or browser default
TextDomain::configureForUser();

require_once(INCLUDE_DIR.'class.staff.php');
require_once(INCLUDE_DIR.'class.csrf.php');

$content = Page::lookupByType('banner-staff');
$thisstaff = StaffAuthenticationBackend::getUser();
$msg = $_SESSION['_staff']['auth']['msg'] ?? null;
$msg = $msg ?: ($content ? $content->getLocalName() : __('Authentication Required'));
$show_reset = false;

if ($_POST) {
    $json = isset($_POST['ajax']) && $_POST['ajax'];
    $respond = function($code, $message) use ($json, $ost) {
        if ($json) {
            $payload = is_array($message) ? $message
                : array('message' => $message);
            $payload['status'] = (int) $code;
            Http::response(200, JSONDataEncoder::encode($payload),
                'application/json');
        }
        else {
            // Extract the `message` portion only
            if (is_array($message))
                $message = $message['message'];
            Http::response($code, $message);
        }
    };

    // Check the CSRF token, and ensure that future requests will have to
    // use a different CSRF token. This will help ward off both parallel and
    // serial brute force attacks, because new tokens will have to be
    // requested for each attempt.
    if (!$ost->checkCSRFToken()) {
        $_SESSION['_staff']['auth']['msg'] = __('Valid CSRF Token Required');
        session_write_close();
        Http::redirect($_SERVER['REQUEST_URI']);
        exit;
    }

}
if ($_POST && isset($_POST['userid'])) {
    error_log('[AUTH] Login started');
    error_log('[AUTH DEBUG] userid='.$_POST['userid']);
    // Lookup support backends for this staff
    $username = trim($_POST['userid']);
    if (Validator::is_userid($username, $errors['err'], false)
        && ($user = StaffAuthenticationBackend::process($username,
            substr($_POST['passwd'], 0, 128), $errors))) {
        
        if ($user) {
            error_log('[AUTH DEBUG] User exists');
            error_log('[AUTH DEBUG] User object='.print_r($user,true));
        }

        if ($user && $user->isValid()) {
            error_log('[AUTH DEBUG] User valid');
            error_log('[AUTH] Session created');
            error_log('[AUTH DEBUG] Redirecting to SCP');
            error_log('[AUTH] Redirect success');
            session_write_close();
            Http::redirect(ROOT_PATH . 'scp/');
            exit;
        } else {
            error_log('[AUTH ERROR] Authentication failed');
            session_write_close();
            Http::redirect('login.php');
            exit;
        }
    }

    error_log('[AUTH ERROR] Authentication failed');
    $msg = $errors['err'] ?: __('Invalid login');
    $show_reset = true;

    if ($json) {
        $respond(401, ['message' => $msg, 'show_reset' => $show_reset]);
    }
    else {
        // Rotate the CSRF token (original cannot be reused)
        $ost->getCSRF()->rotate();
    }
}
elseif ($_POST
        && !strcmp($_POST['do'], '2fa')
        && $thisstaff
        && $thisstaff->is2FAPending()
        && ($auth=$thisstaff->get2FABackend())) {

    try {
        $form = $auth->getInputForm($_POST);
        if ($form->isValid() && $auth->validate($form, $thisstaff)) {
            session_write_close();
            Http::redirect(ROOT_PATH . 'scp/');
            exit;
        }
    } catch (ExpiredOTP $ex) {
        // Expired or too many attempts
        $thisstaff->logOut();
        session_write_close();
        Http::redirect('login.php');
        exit;
    }

    $msg = __('Invalid Code');
    if ($json) {
        $respond(401, ['message' => $msg]);
    }
    else {
        // Rotate the CSRF token (original cannot be reused)
        $ost->getCSRF()->rotate();
    }
}
elseif (isset($_GET['do'])) {
    switch ($_GET['do']) {
    case 'ext':
        // Lookup external backend
        if ($bk = StaffAuthenticationBackend::getBackend($_GET['bk']))
            $bk->triggerAuth();
    }
    session_write_close();
    Http::redirect('login.php');
    exit;
}
// Consider single sign-on authentication backends
elseif (!$thisstaff || !($thisstaff->getId() || $thisstaff->isValid())) {
    if (($user = StaffAuthenticationBackend::processSignOn($errors, false))
            && ($user instanceof StaffSession)) {
        session_write_close();
        Http::redirect(ROOT_PATH . 'scp/');
        exit;
    } else if (isset($_SESSION['_staff']['auth']['msg'])) {
        $msg = $_SESSION['_staff']['auth']['msg'];
    }
}
elseif ($thisstaff && $thisstaff->isValid()) {
    session_write_close();
    Http::redirect(ROOT_PATH . 'scp/');
    exit;
}

define("OSTSCPINC",TRUE); //Make includes happy!
include_once(INCLUDE_DIR.'staff/login.tpl.php');
?>
