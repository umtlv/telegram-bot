<?php

namespace App\Traits;

use App\Models\User;
use DateTime;
use Exception;

trait Verification
{
    /**
     * @throws Exception
     */
    static function checkDate(string $date, string $format = 'd/m/Y'): string
    {
        if (!preg_match('/(\d{2}\/\d{2}\/\d{4})/', $date))
            throw new Exception('Вы ввели дату в неправильном формате.');
        $date = DateTime::createFromFormat($format, $date);
        $errors = DateTime::getLastErrors();
        if ($errors['warning_count'] !== 0) throw new Exception("Вы ввели неправильную дату.");
        return $date->format('Y-m-d');
    }

    /**
     * @throws Exception
     */
    static function checkNickName(string $nickName)
    {
        if (!preg_match('/^[a-z0-9]+$/i', $nickName))
            throw new Exception('Никнейм может содержать только латинские буквы.');

        $db = User::where('nickname', $nickName)->first();
        if (!empty($db)) throw new Exception('Данный никнейм занят.');
    }
}
