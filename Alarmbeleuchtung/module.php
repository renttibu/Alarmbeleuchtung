<?php

/*
 * @module      Alarmbeleuchtung
 *
 * @prefix      ABEL
 *
 * @file        module.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     4.00-1
 * @date        2020-01-16, 18:00, 1579194000
 * @review      2020-01-17, 08:00, 1579194000
 *
 * @see         https://github.com/ubittner/Alarmbeleuchtung/
 *
 * @guids       Library
 *              {189E699E-48A4-3414-FC41-F5047C7AA273}
 *
 *              Alarmbeleuchtung
 *             	{9C804D2B-54AF-690E-EC36-31BF41690EBA}
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class Alarmbeleuchtung extends IPSModule
{
    // Helper
    use ABEL_alarmLight;

    // Constants
    private const DELAY_MILLISECONDS = 250;

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Register properties
        $this->RegisterProperties();

        // Create profiles
        $this->CreateProfiles();

        // Register variables
        $this->RegisterVariables();

        // Register attributes
        $this->RegisterAttributes();

        // Register timers
        $this->RegisterTimers();
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Set options
        $this->SetOptions();

        // Reset attributes
        $this->ResetAttributes();

        // Disable timers
        $this->DisableTimers();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        // Send debug
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

        }
    }

    protected function KernelReady()
    {
        $this->ApplyChanges();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $this->DeleteProfiles();
    }

    //#################### Request Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'AlarmLight':
                $this->ToggleAlarmLight($Value);
                break;
        }
    }

    //#################### Private

    private function RegisterProperties(): void
    {
        // Visibility
        $this->RegisterPropertyBoolean('EnableAlarmLight', true);
        $this->RegisterPropertyBoolean('EnableAlarmLightState', true);

        // Alarm light
        $this->RegisterPropertyString('AlarmLights', '[]');

        // Switch on delay
        $this->RegisterPropertyInteger('ExecutionDelay', 0);

        // Duty cycle
        $this->RegisterPropertyInteger('DutyCycle', 0);

        // Alarm protocol
        $this->RegisterPropertyInteger('AlarmProtocol', 0);
    }

    private function CreateProfiles(): void
    {
        $profile = 'ABEL.' . $this->InstanceID . '.AlarmLightState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', 'Information', 0);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', 'Warning', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, 'Verzögert', 'Clock', 0xFFFF00);
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['AlarmLightState'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = 'ABEL.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
    }

    private function RegisterVariables(): void
    {
        // Alarm light
        $this->RegisterVariableBoolean('AlarmLight', 'Alarmbeleuchtung', '~Switch', 1);
        $this->EnableAction('AlarmLight');
        $id = $this->GetIDForIdent('AlarmLight');
        IPS_SetIcon($id, 'Bulb');

        // Alarm light state
        $profile = 'ABEL.' . $this->InstanceID . '.AlarmLightState';
        $this->RegisterVariableInteger('AlarmLightState', 'Status', $profile, 2);
        $this->SetValue('AlarmLightState', 0);
    }

    private function SetOptions(): void
    {
        // Alarm light
        $id = $this->GetIDForIdent('AlarmLight');
        $use = $this->ReadPropertyBoolean('EnableAlarmLight');
        IPS_SetHidden($id, !$use);

        // Alarm light state
        $id = $this->GetIDForIdent('AlarmLightState');
        $use = $this->ReadPropertyBoolean('EnableAlarmLightState');
        IPS_SetHidden($id, !$use);
    }

    private function RegisterAttributes(): void
    {
        $this->RegisterAttributeBoolean('AlarmLightActive', false);
    }

    private function ResetAttributes(): void
    {
        $this->WriteAttributeBoolean('AlarmLightActive', false);
    }

    private function RegisterTimers(): void
    {
        $this->RegisterTimer('ActivateAlarmLight', 0, 'ABEL_ActivateAlarmLight(' . $this->InstanceID . ');');
        $this->RegisterTimer('DeactivateAlarmLight', 0, 'ABEL_DeactivateAlarmLight(' . $this->InstanceID . ');');
    }

    private function DisableTimers(): void
    {
        $this->SetTimerInterval('ActivateAlarmLight', 0);
        $this->SetTimerInterval('DeactivateAlarmLight', 0);
    }
}