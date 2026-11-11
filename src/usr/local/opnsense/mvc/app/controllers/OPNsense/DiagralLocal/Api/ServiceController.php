<?php
namespace OPNsense\DiagralLocal\Api;
class ServiceController extends \OPNsense\Base\ApiControllerBase {
    private function runRouting($action) {
        return json_decode((new \OPNsense\Core\Backend())->configdRun('diagrallocal '.$action), true)
            ?? ['mode'=>'unknown', 'error'=>'Routing verification failed'];
    }
    public function statusAction() { return $this->runRouting('status'); }
    public function localAction() {
        if (!$this->request->isPost()) return ['mode'=>'unknown','error'=>'POST required'];
        return $this->runRouting('local');
    }
    public function cloudAction() {
        if (!$this->request->isPost()) return ['mode'=>'unknown','error'=>'POST required'];
        return $this->runRouting('cloud');
    }
}
