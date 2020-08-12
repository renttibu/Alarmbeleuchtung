<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class AlarmbeleuchtungValidationTest extends TestCaseSymconValidation
{
    public function testValidateAlarmbeleuchtung(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateAlarmbeleuchtungModule(): void
    {
        $this->validateModule(__DIR__ . '/../Alarmbeleuchtung');
    }
}