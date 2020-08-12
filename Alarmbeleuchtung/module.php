<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

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
 * @see         https://github.com/ubittner/Alarmbeleuchtung
 *
 * @guids       Library
 *              {189E699E-48A4-3414-FC41-F5047C7AA273}
 *
 *              Alarmbeleuchtung
 *             	{9C804D2B-54AF-690E-EC36-31BF41690EBA}
 */

declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class Alarmbeleuchtung extends IPSModule
{
    // Helper
    use ABEL_alarmLight;
    use ABEL_backupRestore;

    // Constants
    private const DELAY_MILLISECONDS = 250;
    private const ALARMBELEUCHTUNG_LIBRARY_GUID = '{189E699E-48A4-3414-FC41-F5047C7AA273}';
    private const ALARMBELEUCHTUNG_MODULE_GUID = '{9C804D2B-54AF-690E-EC36-31BF41690EBA}';

    public function Create()
    {
        // Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->CreateProfiles();
        $this->RegisterVariables();
        $this->RegisterAttributes();
        $this->RegisterTimers();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
        $this->DeleteProfiles();
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
        $this->SetOptions();
        $this->ResetAttributes();
        $this->DisableTimers();
        $this->CheckMaintenanceMode();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $moduleInfo = [];
        $library = IPS_GetLibrary(self::ALARMBELEUCHTUNG_LIBRARY_GUID);
        $module = IPS_GetModule(self::ALARMBELEUCHTUNG_MODULE_GUID);
        $moduleInfo['name'] = $module['ModuleName'];
        $moduleInfo['version'] = $library['Version'] . '-' . $library['Build'];
        $moduleInfo['date'] = date('d.m.Y', $library['Date']);
        $moduleInfo['time'] = date('H:i', $library['Date']);
        $moduleInfo['developer'] = $library['Author'];
        $formData['elements'][0]['items'][2]['caption'] = "Instanz ID:\t\t" . $this->InstanceID;
        $formData['elements'][0]['items'][3]['caption'] = "Modul:\t\t\t" . $moduleInfo['name'];
        $formData['elements'][0]['items'][4]['caption'] = "Version:\t\t\t" . $moduleInfo['version'];
        $formData['elements'][0]['items'][5]['caption'] = "Datum:\t\t\t" . $moduleInfo['date'];
        $formData['elements'][0]['items'][6]['caption'] = "Uhrzeit:\t\t\t" . $moduleInfo['time'];
        $formData['elements'][0]['items'][7]['caption'] = "Entwickler:\t\t" . $moduleInfo['developer'];
        $formData['elements'][0]['items'][8]['caption'] = "Präfix:\t\t\tABEL";
        return json_encode($formData);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    #################### Request Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'AlarmLight':
                $this->ToggleAlarmLight($Value);
                break;
        }
    }

    #################### Private

    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    private function RegisterProperties(): void
    {
        $this->RegisterPropertyString('Note', '');
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
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
        $this->RegisterVariableBoolean('AlarmLight', 'Alarmbeleuchtung', '~Switch', 10);
        $this->EnableAction('AlarmLight');
        $id = $this->GetIDForIdent('AlarmLight');
        IPS_SetIcon($id, 'Bulb');
        // Alarm light state
        $profile = 'ABEL.' . $this->InstanceID . '.AlarmLightState';
        $this->RegisterVariableInteger('AlarmLightState', 'Status', $profile, 20);
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

    private function CheckMaintenanceMode(): bool
    {
        $result = false;
        $status = 102;
        if ($this->ReadPropertyBoolean('MaintenanceMode')) {
            $result = true;
            $status = 104;
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        $this->SetStatus($status);
        IPS_SetDisabled($this->InstanceID, $result);
        return $result;
    }
}