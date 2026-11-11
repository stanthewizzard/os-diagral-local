<?php
namespace OPNsense\DiagralLocal;
class GeneralController extends \OPNsense\Base\IndexController {
    public function indexAction() {
        $this->view->pick('OPNsense/DiagralLocal/general');
        $this->view->generalForm = $this->getForm('general');
    }
}
