<?php

/** @noinspection PhpUnused */
/** @noinspection PhpUndefinedFunctionInspection */

declare(strict_types=1);

trait AB1_alarmLight
{
    #################### Variable

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
        $result = false;
        //Check maintenance mode
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        //Check alarm light
        if (!$this->CheckAlarmLight()) {
            return $result;
        }
        $id = $this->ReadPropertyInteger('AlarmLight');
        $actualAlarmLightState = $this->GetValue('AlarmLight');
        //Deactivate
        if (!$State) {
            $this->SendDebug(__FUNCTION__, 'Die Alarmbeleuchtung wird ausgeschaltet', 0);
            $this->DisableTimers();
            $this->SetValue('AlarmLight', false);
            IPS_Sleep($this->ReadPropertyInteger('AlarmLightSwitchingDelay'));
            //Semaphore Enter
            if (!IPS_SemaphoreEnter($this->InstanceID . '.ToggleAlarmLight', 5000)) {
                return $result;
            }
            $result = true;
            $response = @RequestAction($id, false);
            if (!$response) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $response = @RequestAction($id, false);
                if (!$response) {
                    $result = false;
                    //Revert
                    $this->SetValue('AlarmLight', $actualAlarmLightState);
                    $message = 'Fehler, die Alarmbeleuchtung konnte nicht ausgeschaltet werden!';
                    $this->SendDebug(__FUNCTION__, $message, 0);
                    $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $message, KL_ERROR);
                }
            }
            //Semaphore leave
            IPS_SemaphoreLeave($this->InstanceID . '.ToggleAlarmLight');
            if ($result) {
                $this->SendDebug(__FUNCTION__, 'Die Alarmbeleuchtung wurde erfolgreich ausgeschaltet', 0);
                //Protocol
                $text = 'Die Alarmbeleuchtung wurde ausgeschaltet. (ID ' . $id . ')';
                $this->UpdateAlarmProtocol($text);
            }
        }
        //Activate
        if ($State) {
            //Check night mode
            if ($this->CheckNightMode()) {
                return $result;
            }
            $this->SetValue('AlarmLight', true);
            //Delay
            $delay = $this->ReadPropertyInteger('SwitchOnDelay');
            if ($delay > 0) {
                $this->SetTimerInterval('ActivateAlarmLight', $delay * 1000);
                $unit = 'Sekunden';
                if ($delay == 1) {
                    $unit = 'Sekunde';
                }
                $this->SendDebug(__FUNCTION__, 'Einschaltverzögerung, die Alarmbeleuchtung wird in ' . $delay . ' ' . $unit . ' eingeschaltet', 0);
                if (!$actualAlarmLightState) {
                    //Protocol
                    $text = 'Die Alarmbeleuchtung wird in ' . $delay . ' Sekunden eingeschaltet. (ID ' . $id . ')';
                    $this->UpdateAlarmProtocol($text);
                }
            }
            //No delay, activate alarm light immediately
            else {
                $result = $this->ActivateAlarmLight();
                if (!$result) {
                    //Revert
                    $this->SetValue('AlarmLight', $actualAlarmLightState);
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
        $result = false;
        //Check maintenance mode
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        //Check night mode
        if ($this->CheckNightMode()) {
            return $result;
        }
        //Check alarm light
        if (!$this->CheckAlarmLight()) {
            return $result;
        }
        $this->SetTimerInterval('ActivateAlarmLight', 0);
        $this->SetValue('AlarmLight', true);
        $duration = $this->ReadPropertyInteger('SwitchOnDuration');
        IPS_Sleep($this->ReadPropertyInteger('AlarmLightSwitchingDelay'));
        //Semaphore Enter
        if (!IPS_SemaphoreEnter($this->InstanceID . '.ActivateAlarmLight', 5000)) {
            return $result;
        }
        $result = true;
        $id = $this->ReadPropertyInteger('AlarmLight');
        $this->SendDebug(__FUNCTION__, 'Die Alarmbeleuchtung wird eingeschaltet', 0);
        $response = @RequestAction($id, true);
        if (!$response) {
            IPS_Sleep(self::DELAY_MILLISECONDS);
            $response = @RequestAction($id, true);
            if (!$response) {
                $result = false;
                $message = 'Fehler, die Alarmbeleuchtung konnte nicht eingeschaltet werden!';
                $this->SendDebug(__FUNCTION__, $message, 0);
                $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $message, KL_ERROR);
            }
        }
        //Semaphore leave
        IPS_SemaphoreLeave($this->InstanceID . '.ActivateAlarmLight');
        if ($result) {
            $this->SendDebug(__FUNCTION__, 'Die Alarmbeleuchtung wurde erfolgreich eingeschaltet', 0);
            //Protocol
            $text = 'Die Alarmbeleuchtung wurde eingeschaltet. (ID ' . $id . ')';
            $this->UpdateAlarmProtocol($text);
        }
        $this->SetTimerInterval('DeactivateAlarmLight', $duration * 60 * 1000);
        if ($duration > 0) {
            $unit = 'Minuten';
            if ($duration == 1) {
                $unit = 'Minute';
            }
            $this->SendDebug(__FUNCTION__, 'Einschaltdauer, die Alarmbeleuchtung wird in ' . $duration . ' ' . $unit . ' automatisch ausgeschaltet', 0);
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
        $result = false;
        //Check maintenance mode
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        $this->SetTimerInterval('DeactivateAlarmLight', 0);
        $result = $this->ToggleAlarmLight(false);
        return $result;
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
        $result = false;
        //Check maintenance mode
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        //Check night mode
        if ($this->CheckNightMode()) {
            return $result;
        }
        $result = true;
        //Trigger variables
        $triggerVariables = json_decode($this->ReadPropertyString('TriggerVariables'));
        if (!empty($triggerVariables)) {
            foreach ($triggerVariables as $variable) {
                $id = $variable->ID;
                if ($SenderID == $id) {
                    $use = $variable->ID;
                    if ($use) {
                        $triggerValueOn = $variable->TriggerValueOn;
                        $this->SendDebug(__FUNCTION__, 'Benötigter Einschaltwert: ' . $triggerValueOn, 0);
                        $triggerValueOff = $variable->TriggerValueOff;
                        $this->SendDebug(__FUNCTION__, 'Benötigter Ausschaltwert: ' . $triggerValueOff, 0);
                        $actualValue = intval(GetValue($id));
                        $this->SendDebug(__FUNCTION__, 'Aktueller Wert: ' . $actualValue, 0);
                        $trigger = false;
                        if ($actualValue == $triggerValueOn) {
                            $trigger = true;
                            $this->SendDebug(__FUNCTION__, 'Einschalten wurde ausgelöst', 0);
                            $result = $this->ToggleAlarmLight(true);
                        }
                        if ($actualValue == $triggerValueOff) {
                            $trigger = true;
                            $this->SendDebug(__FUNCTION__, 'Ausschalten wurde ausgelöst', 0);
                            $result = $this->ToggleAlarmLight(false);
                        }
                        if (!$trigger) {
                            $this->SendDebug(__FUNCTION__, 'Es wurde keine Aktion ausgelöst', 0);
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
     * false    = no alarm siren
     * true     = ok
     */
    private function CheckAlarmLight(): bool
    {
        $result = true;
        $id = $this->ReadPropertyInteger('AlarmLight');
        if ($id == 0 || @!IPS_ObjectExists($id)) {
            $result = false;
            $message = 'Abbruch, es ist keine Alarmbeleuchtung ausgewählt!';
            $this->SendDebug(__FUNCTION__, $message, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $message, KL_WARNING);
        }
        return $result;
    }

    /**
     * Updates the alarm protocol.
     *
     * @param string $Message
     */
    private function UpdateAlarmProtocol(string $Message): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $protocolID = $this->ReadPropertyInteger('AlarmProtocol');
        if ($protocolID != 0 && @IPS_ObjectExists($protocolID)) {
            $timestamp = date('d.m.Y, H:i:s');
            $logText = $timestamp . ', ' . $Message;
            @APRO_UpdateMessages($protocolID, $logText, 0);
            $this->SendDebug(__FUNCTION__, 'Das Alarmprotokoll wurde aktualisiert', 0);
        }
    }
}