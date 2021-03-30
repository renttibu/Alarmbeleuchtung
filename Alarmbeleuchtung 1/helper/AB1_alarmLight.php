<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmbeleuchtung/tree/master/Alarmbeleuchtung%201
 */

/** @noinspection PhpUnused */

declare(strict_types=1);

trait AB1_alarmLight
{
    public function ToggleAlarmLight(bool $State): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird mit dem Parameter ' . json_encode($State) . ' ausgeführt.', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if (!$this->CheckSwitchingVariable()) {
            return false;
        }
        $result = true;
        $id = $this->ReadPropertyInteger('Variable');
        $actualAlarmLightState = $this->GetValue('AlarmLight');
        // Deactivate
        if (!$State) {
            $this->SendDebug(__FUNCTION__, 'Die Alarmbeleuchtung wird ausgeschaltet', 0);
            $this->SetTimerInterval('ActivateAlarmLight', 0);
            $this->SetTimerInterval('DeactivateAlarmLight', 0);
            IPS_Sleep($this->ReadPropertyInteger('AlarmLightSwitchingDelay'));
            // Semaphore enter
            if (!IPS_SemaphoreEnter($this->InstanceID . '.ToggleAlarmLight', 5000)) {
                return false;
            }
            $this->SetValue('AlarmLight', false);
            $response = @RequestAction($id, false);
            if (!$response) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $response = @RequestAction($id, false);
                if (!$response) {
                    IPS_Sleep(self::DELAY_MILLISECONDS * 2);
                    $response = @RequestAction($id, false);
                    if (!$response) {
                        $result = false;
                    }
                }
            }
            // Semaphore leave
            IPS_SemaphoreLeave($this->InstanceID . '.ToggleAlarmLight');
            if ($result) {
                $text = 'Die Alarmbeleuchtung wurde ausgeschaltet';
                $this->SendDebug(__FUNCTION__, $text, 0);
                // Protocol
                if ($State != $actualAlarmLightState) {
                    $this->UpdateAlarmProtocol($text . '. (ID ' . $id . ')');
                }
            } else {
                // Revert on failure
                $this->SetValue('AlarmLight', $actualAlarmLightState);
                // Log
                $text = 'Fehler, die Alarmbeleuchtung konnte nicht ausgeschaltet werden!';
                $this->SendDebug(__FUNCTION__, $text, 0);
                $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
                // Protocol
                if ($State != $actualAlarmLightState) {
                    $this->UpdateAlarmProtocol($text . ' (ID ' . $id . ')');
                }
            }
        }
        // Activate
        if ($State) {
            if ($this->CheckNightMode()) {
                return false;
            }
            // Delay
            $delay = $this->ReadPropertyInteger('SwitchOnDelay');
            if ($delay > 0) {
                $this->SetTimerInterval('ActivateAlarmLight', $delay * 1000);
                $unit = 'Sekunden';
                if ($delay == 1) {
                    $unit = 'Sekunde';
                }
                $this->SetValue('AlarmLight', true);
                $text = 'Die Alarmbeleuchtung wird in ' . $delay . ' ' . $unit . ' eingeschaltet';
                $this->SendDebug(__FUNCTION__, $text, 0);
                if (!$actualAlarmLightState) {
                    // Protocol
                    if ($State != $actualAlarmLightState) {
                        $this->UpdateAlarmProtocol($text . '. (ID ' . $id . ')');
                    }
                }
            }
            // No delay, activate alarm light immediately
            else {
                if ($State != $actualAlarmLightState) {
                    $result = $this->ActivateAlarmLight();
                }
            }
        }
        return $result;
    }

    public function ActivateAlarmLight(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        $this->SetTimerInterval('ActivateAlarmLight', 0);
        if (!$this->CheckSwitchingVariable()) {
            return false;
        }
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->CheckNightMode()) {
            return false;
        }
        return $this->TriggerAlarmLight();
    }

    public function TriggerAlarmLight(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        $this->SetTimerInterval('ActivateAlarmLight', 0);
        if (!$this->CheckSwitchingVariable()) {
            return false;
        }
        IPS_Sleep($this->ReadPropertyInteger('AlarmLightSwitchingDelay'));
        // Semaphore enter
        if (!IPS_SemaphoreEnter($this->InstanceID . '.ActivateAlarmLight', 5000)) {
            return false;
        }
        $this->SendDebug(__FUNCTION__, 'Die Alarmbeleuchtung wird eingeschaltet', 0);
        $this->SetValue('AlarmLight', true);
        $result = true;
        $id = $this->ReadPropertyInteger('Variable');
        $response = @RequestAction($id, true);
        if (!$response) {
            IPS_Sleep(self::DELAY_MILLISECONDS);
            $response = @RequestAction($id, true);
            if (!$response) {
                $result = false;
            }
        }
        // Semaphore leave
        IPS_SemaphoreLeave($this->InstanceID . '.ActivateAlarmLight');
        if ($result) {
            $text = 'Die Alarmbeleuchtung wurde eingeschaltet';
            $this->SendDebug(__FUNCTION__, $text, 0);
            // Protocol
            $this->UpdateAlarmProtocol($text . '. (ID ' . $id . ')');
            // Switch on duration
            $duration = $this->ReadPropertyInteger('SwitchOnDuration');
            $this->SetTimerInterval('DeactivateAlarmLight', $duration * 60 * 1000);
            if ($duration > 0) {
                $unit = 'Minuten';
                if ($duration == 1) {
                    $unit = 'Minute';
                }
                $this->SendDebug(__FUNCTION__, 'Einschaltdauer, die Alarmbeleuchtung wird in ' . $duration . ' ' . $unit . ' automatisch ausgeschaltet', 0);
            }
        } else {
            // Revert on failure
            $this->SetValue('AlarmLight', false);
            // Log
            $text = 'Fehler, die Alarmbeleuchtung konnte nicht eingeschaltet werden!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
            // Protocol
            $this->UpdateAlarmProtocol($text . ' (ID ' . $id . ')');
        }
        return $result;
    }

    public function DeactivateAlarmLight(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        $this->SetTimerInterval('DeactivateAlarmLight', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        return $this->ToggleAlarmLight(false);
    }

    public function CheckTriggerVariable(int $SenderID, bool $ValueChanged): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        $this->SendDebug(__FUNCTION__, 'Sender: ' . $SenderID . ', Wert hat sich geändert: ' . json_encode($ValueChanged), 0);
        if (!$this->CheckSwitchingVariable()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('TriggerVariables'));
        if (empty($vars)) {
            return false;
        }
        $result = false;
        foreach ($vars as $var) {
            $execute = false;
            $id = $var->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                if ($var->Use) {
                    $this->SendDebug(__FUNCTION__, 'Variable: ' . $id . ' ist aktiviert', 0);
                    $type = IPS_GetVariable($id)['VariableType'];
                    $value = $var->Value;
                    switch ($var->Trigger) {
                        case 0: # on change (bool, integer, float, string)
                            $this->SendDebug(__FUNCTION__, 'Bei Änderung (bool, integer, float, string)', 0);
                            if ($ValueChanged) {
                                $execute = true;
                            }
                            break;

                        case 1: # on update (bool, integer, float, string)
                            $this->SendDebug(__FUNCTION__, 'Bei Aktualisierung (bool, integer, float, string)', 0);
                            $execute = true;
                            break;

                        case 2: # on limit drop, once (integer, float)
                            switch ($type) {
                                case 1: # integer
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (integer)', 0);
                                    if ($ValueChanged) {
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if ($value == 'true') {
                                            $value = '1';
                                        }
                                        if (GetValueInteger($SenderID) < intval($value)) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                                case 2: # float
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (float)', 0);
                                    if ($ValueChanged) {
                                        if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $value))) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                            }
                            break;

                        case 3: # on limit drop, every time (integer, float)
                            switch ($type) {
                                case 1: # integer
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if ($value == 'true') {
                                        $value = '1';
                                    }
                                    if (GetValueInteger($SenderID) < intval($value)) {
                                        $execute = true;
                                    }
                                    break;

                                case 2: # float
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (float)', 0);
                                    if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $value))) {
                                        $execute = true;
                                    }
                                    break;

                            }
                            break;

                        case 4: # on limit exceed, once (integer, float)
                            switch ($type) {
                                case 1: # integer
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (integer)', 0);
                                    if ($ValueChanged) {
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if ($value == 'true') {
                                            $value = '1';
                                        }
                                        if (GetValueInteger($SenderID) > intval($value)) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                                case 2: # float
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (float)', 0);
                                    if ($ValueChanged) {
                                        if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $value))) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                            }
                            break;

                        case 5: # on limit exceed, every time (integer, float)
                            switch ($type) {
                                case 1: # integer
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if ($value == 'true') {
                                        $value = '1';
                                    }
                                    if (GetValueInteger($SenderID) > intval($value)) {
                                        $execute = true;
                                    }
                                    break;

                                case 2: # float
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (float)', 0);
                                    if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $value))) {
                                        $execute = true;
                                    }
                                    break;

                            }
                            break;

                        case 6: # on specific value, once (bool, integer, float, string)
                            switch ($type) {
                                case 0: # bool
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (bool)', 0);
                                    if ($ValueChanged) {
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if (GetValueBoolean($SenderID) == boolval($value)) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                                case 1: # integer
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (integer)', 0);
                                    if ($ValueChanged) {
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if ($value == 'true') {
                                            $value = '1';
                                        }
                                        if (GetValueInteger($SenderID) == intval($value)) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                                case 2: # float
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (float)', 0);
                                    if ($ValueChanged) {
                                        if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $value))) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                                case 3: # string
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (string)', 0);
                                    if ($ValueChanged) {
                                        if (GetValueString($SenderID) == (string) $value) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                            }
                            break;

                        case 7: # on specific value, every time (bool, integer, float, string)
                            switch ($type) {
                                case 0: # bool
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (bool)', 0);
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if (GetValueBoolean($SenderID) == boolval($value)) {
                                        $execute = true;
                                    }
                                    break;

                                case 1: # integer
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (integer)', 0);
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if ($value == 'true') {
                                        $value = '1';
                                    }
                                    if (GetValueInteger($SenderID) == intval($value)) {
                                        $execute = true;
                                    }
                                    break;

                                case 2: # float
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (float)', 0);
                                    if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $value))) {
                                        $execute = true;
                                    }
                                    break;

                                case 3: # string
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (string)', 0);
                                    if (GetValueString($SenderID) == (string) $value) {
                                        $execute = true;
                                    }
                                    break;

                            }
                            break;

                    }
                    $this->SendDebug(__FUNCTION__, 'Bedingung erfüllt: ' . json_encode($execute), 0);
                    if ($execute) {
                        $action = $var->Action;
                        switch ($action) {
                            case 0:
                                $this->SendDebug(__FUNCTION__, 'Aktion: Alarmbeleuchtung ausschalten', 0);
                                $result = $this->ToggleAlarmLight(false);
                                break;

                            case 1:
                                $this->SendDebug(__FUNCTION__, 'Aktion: Alarmbeleuchtung einschalten', 0);
                                if ($this->CheckMaintenanceMode()) {
                                    return false;
                                }
                                if ($this->CheckNightMode()) {
                                    return false;
                                }
                                $result = $this->ToggleAlarmLight(true);
                                break;

                            case 2:
                                $this->SendDebug(__FUNCTION__, 'Aktion: Panikbeleuchtung', 0);
                                if ($this->CheckMaintenanceMode()) {
                                    return false;
                                }
                                $result = $this->TriggerAlarmLight();
                                break;

                            default:
                                $this->SendDebug(__FUNCTION__, 'Es soll keine Aktion erfolgen!', 0);
                        }
                    } else {
                        $this->SendDebug(__FUNCTION__, 'Keine Übereinstimmung!', 0);
                    }
                }
            }
        }
        return $result;
    }

    #################### Private

    private function CheckSwitchingVariable(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        $id = $this->ReadPropertyInteger('Variable');
        if ($id == 0 || @!IPS_ObjectExists($id)) {
            $text = 'Abbruch, es ist kein Variable ausgewählt!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_WARNING);
            return false;
        }
        return true;
    }
}