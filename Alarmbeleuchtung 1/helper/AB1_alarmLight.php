<?php

/** @noinspection PhpUnused */

/*
 * @module      Alarmbeleuchtung 1 (Variable)
 *
 * @prefix      AB1
 *
 * @file        AB1_alarmLight.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @see         https://github.com/ubittner/Alarmbeleuchtung
 *
 */

declare(strict_types=1);

trait AB1_alarmLight
{
    /**
     * Toggles the alarm light off or on.
     *
     * @param bool $State
     * false    = off
     * true     = on
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function ToggleAlarmLight(bool $State): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wurde mit Parameter ' . json_encode($State) . ' aufgerufen (' . microtime(true) . ')', 0);
        $this->DisableTimers();
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if (!$this->CheckSwitchingVariable()) {
            return false;
        }
        $result = true;
        $id = $this->ReadPropertyInteger('Variable');
        $actualAlarmLightState = $this->GetValue('AlarmLight');
        //Deactivate
        if (!$State) {
            $this->SendDebug(__FUNCTION__, 'Die Alarmbeleuchtung wird ausgeschaltet', 0);
            IPS_Sleep($this->ReadPropertyInteger('AlarmLightSwitchingDelay'));
            //Semaphore Enter
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
            //Semaphore leave
            IPS_SemaphoreLeave($this->InstanceID . '.ToggleAlarmLight');
            if ($result) {
                $text = 'Die Alarmbeleuchtung wurde ausgeschaltet';
                $this->SendDebug(__FUNCTION__, $text, 0);
                //Protocol
                if ($State != $actualAlarmLightState) {
                    $this->UpdateAlarmProtocol($text . '. (ID ' . $id . ')');
                }
            } else {
                //Revert on failure
                $this->SetValue('AlarmLight', $actualAlarmLightState);
                //Log
                $text = 'Fehler, die Alarmbeleuchtung konnte nicht ausgeschaltet werden!';
                $this->SendDebug(__FUNCTION__, $text, 0);
                $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
                //Protocol
                if ($State != $actualAlarmLightState) {
                    $this->UpdateAlarmProtocol($text . ' (ID ' . $id . ')');
                }
            }
        }
        //Activate
        if ($State) {
            if ($this->CheckNightMode()) {
                return false;
            }
            //Delay
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
                    //Protocol
                    if ($State != $actualAlarmLightState) {
                        $this->UpdateAlarmProtocol($text . '. (ID ' . $id . ')');
                    }
                }
            }
            //No delay, activate alarm light immediately
            else {
                if ($State != $actualAlarmLightState) {
                    $result = $this->ActivateAlarmLight();
                }
            }
        }
        return $result;
    }

    /**
     * Activates the alarm light.
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function ActivateAlarmLight(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
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
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SetTimerInterval('ActivateAlarmLight', 0);
        if (!$this->CheckSwitchingVariable()) {
            return false;
        }
        IPS_Sleep($this->ReadPropertyInteger('AlarmLightSwitchingDelay'));
        //Semaphore Enter
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
        //Semaphore leave
        IPS_SemaphoreLeave($this->InstanceID . '.ActivateAlarmLight');
        if ($result) {
            $text = 'Die Alarmbeleuchtung wurde eingeschaltet';
            $this->SendDebug(__FUNCTION__, $text, 0);
            //Protocol
            $this->UpdateAlarmProtocol($text . '. (ID ' . $id . ')');
            //Switch on duration
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
            //Revert on failure
            $this->SetValue('AlarmLight', false);
            //Log
            $text = 'Fehler, die Alarmbeleuchtung konnte nicht eingeschaltet werden!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
            //Protocol
            $this->UpdateAlarmProtocol($text . ' (ID ' . $id . ')');
        }
        return $result;
    }

    /**
     * Deactivates the alarm light.
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function DeactivateAlarmLight(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SetTimerInterval('DeactivateAlarmLight', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        return $this->ToggleAlarmLight(false);
    }

    /**
     * Checks the trigger variable.
     *
     * @param int $SenderID
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function CheckTrigger(int $SenderID): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wurde vom Sender ' . $SenderID . ' aufgerufen (' . microtime(true) . ')', 0);
        if (!$this->CheckSwitchingVariable()) {
            return false;
        }
        $result = true;
        //Trigger variables
        $triggerVariables = json_decode($this->ReadPropertyString('TriggerVariables'));
        if (!empty($triggerVariables)) {
            foreach ($triggerVariables as $variable) {
                $id = $variable->ID;
                if ($SenderID == $id) {
                    if ($variable->Use) {
                        $actualValue = intval(GetValue($id));
                        $this->SendDebug(__FUNCTION__, 'Aktueller Wert: ' . $actualValue, 0);
                        $triggerValue = $variable->TriggerValue;
                        $this->SendDebug(__FUNCTION__, 'Auslösender Wert: ' . $triggerValue, 0);
                        if ($actualValue == $triggerValue) {
                            $triggerAction = $variable->TriggerAction;
                            switch ($triggerAction) {
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
        }
        return $result;
    }

    #################### Private

    /**
     * Checks for an existing alarm light.
     *
     * @return bool
     * false    = no alarm light
     * true     = ok
     */
    private function CheckSwitchingVariable(): bool
    {
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