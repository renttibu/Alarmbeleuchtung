<?php

/** @noinspection PhpUndefinedFunctionInspection */

declare(strict_types=1);

trait ABEL_alarmLight
{
    #################### Public

    /**
     * Toggles the alarm light.
     *
     * @param bool $State
     * false    = off
     * true     = on
     */
    public function ToggleAlarmLight(bool $State): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $this->SendDebug(__FUNCTION__, 'Die Methode wurde mit Parameter ' . json_encode($State) . ' aufgerufen.', 0);
        // Check alarm lights
        if (!$this->CheckExecution()) {
            return;
        }
        $count = $this->GetAlarmLightAmount();
        $lastState = $this->GetValue('AlarmLight');
        // Deactivate
        if (!$State) {
            $this->DeactivateAlarmLight();
        }
        // Activate
        if ($State) {
            // Check if alarm light is already powered on
            if ($this->CheckAlarmLightState()) {
                return;
            }
            $this->SendDebug(__FUNCTION__, 'Die Alarmbeleuchtungen werden eingeschaltet.', 0);
            $this->SetValue('AlarmLight', true);
            // Delay
            $delay = $this->ReadPropertyInteger('ExecutionDelay');
            if ($delay > 0) {
                $this->SetValue('AlarmLightState', 2);
                $this->SetTimerInterval('ActivateAlarmLight', $delay * 1000);
                // Alarm lights
                if ($count > 0) {
                    $alarmLights = json_decode($this->ReadPropertyString('AlarmLights'));
                    foreach ($alarmLights as $alarmLight) {
                        if ($alarmLight->Use) {
                            $id = $alarmLight->ID;
                            if ($id != 0 && @IPS_ObjectExists($id)) {
                                $delayed = true;
                                $type = $alarmLight->Type;
                                if ($type == 2) {
                                    $delayed = @IPS_RunScriptEx($id, ['State' => 2]);
                                }
                                // Log & Debug
                                if (!$delayed) {
                                    $text = 'Die Alarmbeleuchtung konnte nicht verzögert eingeschaltet werden. (ID ' . $id . ')';
                                    $this->LogMessage(__FUNCTION__ . ', ' . $text, KL_ERROR);
                                } else {
                                    $text = 'Die Alarmbeleuchtung wird verzögert in ' . $delay . ' Sekunden eingeschaltet. (ID ' . $id . ')';
                                }
                                $this->SendDebug(__FUNCTION__, $text, 0);
                                // Protocol
                                if (!$lastState) {
                                    $this->UpdateProtocol($text);
                                }
                            }
                        }
                    }
                }
            } // No delay, activate alarm light immediately
            else {
                $this->SetValue('AlarmLightState', 1);
                $this->ActivateAlarmLight();
            }
        }
    }

    /**
     * Deactivates the alarm light.
     */
    public function DeactivateAlarmLight(): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $this->SendDebug(__FUNCTION__, 'Die Methode wurde aufgerufen.', 0);
        // Check alarm lights
        if (!$this->CheckExecution()) {
            return;
        }
        $this->SendDebug(__FUNCTION__, 'Die Alarmbeleuchtungen werden ausgeschaltet.', 0);
        $this->WriteAttributeBoolean('AlarmLightActive', false);
        $this->SendDebug(__FUNCTION__, 'Attribute AlarmLightActive: false', 0);
        $this->DisableTimers();
        $lastState = $this->GetValue('AlarmLight');
        $this->SetValue('AlarmLight', false);
        $this->SetValue('AlarmLightState', 0);
        $count = $this->GetAlarmLightAmount();
        if ($count > 0) {
            $i = 0;
            $alarmLights = json_decode($this->ReadPropertyString('AlarmLights'));
            foreach ($alarmLights as $alarmLight) {
                if ($alarmLight->Use) {
                    $id = $alarmLight->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $i++;
                        $type = $alarmLight->Type;
                        $deactivate = true;
                        switch ($type) {
                            // Variable
                            case 1:
                                $deactivate = @RequestAction($id, false);
                                break;

                            // Script
                            case 2:
                                $deactivate = @IPS_RunScriptEx($id, ['State' => 0]);
                                break;

                        }
                        // Log & Debug
                        if (!$deactivate) {
                            $text = 'Die Alarmbeleuchtung konnte nicht ausgeschaltet werden. (ID ' . $id . ')';
                            $this->LogMessage(__FUNCTION__ . ', ' . $text, KL_ERROR);
                        } else {
                            $text = 'Die Alarmbeleuchtung wurde ausgeschaltet. (ID ' . $id . ')';
                        }
                        $this->SendDebug(__FUNCTION__, $text, 0);
                        // Protocol
                        if ($lastState) {
                            $this->UpdateProtocol($text);
                        }
                        // Execution delay for next alarm siren
                        if ($count > 1 && $i < $count) {
                            IPS_Sleep(self::DELAY_MILLISECONDS);
                        }
                    }
                }
            }
        }
    }

    /**
     * Activates the alarm light.
     */
    public function ActivateAlarmLight(): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $this->SendDebug(__FUNCTION__, 'Die Methode wurde aufgerufen.', 0);
        // Check alarm lights
        if (!$this->CheckExecution()) {
            return;
        }
        // Check if alarm light is already powered on
        if ($this->CheckAlarmLightState()) {
            return;
        }
        $this->SendDebug(__FUNCTION__, 'Die Alarmbeleuchtungen werden eingeschaltet.', 0);
        $this->WriteAttributeBoolean('AlarmLightActive', true);
        $this->SendDebug(__FUNCTION__, 'Attribute AlarmLightActive: true', 0);
        $this->DisableTimers();
        $lastState = $this->GetValue('AlarmLight');
        $this->SetValue('AlarmLight', true);
        $this->SetValue('AlarmLightState', 1);
        // Alarm lights
        $count = $this->GetAlarmLightAmount();
        if ($count > 0) {
            $i = 0;
            $alarmLights = json_decode($this->ReadPropertyString('AlarmLights'));
            foreach ($alarmLights as $alarmLight) {
                if ($alarmLight->Use) {
                    $id = $alarmLight->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $i++;
                        $type = $alarmLight->Type;
                        $deactivate = true;
                        switch ($type) {
                            // Variable
                            case 1:
                                $deactivate = @RequestAction($id, true);
                                break;

                            // Script
                            case 2:
                                $deactivate = @IPS_RunScriptEx($id, ['State' => 1]);
                                break;

                        }
                        // Log & Debug
                        if (!$deactivate) {
                            $text = 'Die Alarmbeleuchtung konnte nicht eingeschaltet werden. (ID ' . $id . ')';
                            $this->LogMessage(__FUNCTION__ . ', ' . $text, KL_ERROR);
                        } else {
                            $text = 'Die Alarmbeleuchtung wurde eingeschaltet. (ID ' . $id . ')';
                        }
                        $this->SendDebug(__FUNCTION__, $text, 0);
                        // Protocol
                        if ($lastState) {
                            $this->UpdateProtocol($text);
                        }
                        // Execution delay for next alarm siren
                        if ($count > 1 && $i < $count) {
                            IPS_Sleep(self::DELAY_MILLISECONDS);
                        }
                    }
                }
            }
        }
        // Duty Cycle
        $duration = $this->ReadPropertyInteger('DutyCycle');
        if ($duration > 0) {
            $this->SetTimerInterval('DeactivateAlarmLight', $duration * 60000);
        }
    }

    #################### Private

    /**
     * Checks the execution.
     *
     * @return bool
     * false    = no alarm light exists
     * true     = at least one alarm light exists
     */
    private function CheckExecution(): bool
    {
        $execute = false;
        $alarmLights = json_decode($this->ReadPropertyString('AlarmLights'));
        if (!empty($alarmLights)) {
            foreach ($alarmLights as $alarmLight) {
                if ($alarmLight->Use) {
                    $id = $alarmLight->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $execute = true;
                    }
                }
            }
        }
        // Log & Debug
        if (!$execute) {
            $text = 'Es ist keine Alarmbeleuchtung vorhanden!';
            $this->LogMessage(__FUNCTION__ . ', ' . $text, KL_ERROR);
            $this->SendDebug(__FUNCTION__, $text, 0);
        }
        return $execute;
    }

    /**
     * Gets the amount of alarm lights.
     *
     * @return int
     * Returns the amount of used alarm lights.
     */
    private function GetAlarmLightAmount(): int
    {
        $amount = 0;
        $alarmLights = json_decode($this->ReadPropertyString('AlarmLights'));
        if (!empty($alarmLights)) {
            foreach ($alarmLights as $alarmLight) {
                if ($alarmLight->Use) {
                    $id = $alarmLight->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $amount++;
                    }
                }
            }
        }
        return $amount;
    }

    /**
     * Checks the state of the alarm light.
     *
     * @return bool
     * false    = off
     * true     = on
     */
    private function CheckAlarmLightState(): bool
    {
        $state = false;
        if ($this->ReadAttributeBoolean('AlarmLightActive')) {
            $text = 'Abbruch, Die Alarmbeleuchtung ist bereits eingeschaltet!';
            $this->LogMessage(__FUNCTION__ . ', ' . $text, KL_ERROR);
            $this->SendDebug(__FUNCTION__, $text, 0);
            $state = true;
        }
        return $state;
    }

    /**
     * Updates the protocol.
     *
     * @param string $Message
     */
    private function UpdateProtocol(string $Message): void
    {
        $protocolID = $this->ReadPropertyInteger('AlarmProtocol');
        if ($protocolID != 0 && @IPS_ObjectExists($protocolID)) {
            $timestamp = date('d.m.Y, H:i:s');
            $logText = $timestamp . ', ' . $Message;
            @APRO_UpdateMessages($protocolID, $logText, 0);
        }
    }
}