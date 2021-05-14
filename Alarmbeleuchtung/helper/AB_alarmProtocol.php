<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmbeleuchtung/tree/master/Alarmbeleuchtung
 */

/** @noinspection PhpUnusedPrivateMethodInspection */

declare(strict_types=1);

trait AB_alarmProtocol
{
    private function UpdateAlarmProtocol(string $Message): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $id = $this->ReadPropertyInteger('AlarmProtocol');
        if ($id == 0 || !@IPS_ObjectExists($id)) {
            return;
        }
        $timestamp = date('d.m.Y, H:i:s');
        $logText = $timestamp . ', ' . $Message;
        $logType = 0;
        //@AP_UpdateMessages($id, $logText, $logType);
        $protocol = 'AP_UpdateMessages(' . $id . ', "' . $logText . '", ' . $logType . ');';
        @IPS_RunScriptText($protocol);
    }
}