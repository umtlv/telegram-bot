<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
//use Telegram\Bot\Objects\User;

class TelegramApiController extends Controller
{
    private Api $Telegram;
//    private User $Response;

    public function __construct()
    {
        $this->Telegram = new Api();
//        $this->Response = $this->Telegram->getMe();
    }

    /**
     * @throws TelegramSDKException
     */
    public function handle(Request $request)
    {
        $res = $this->Telegram->getMe();

        $this->Telegram->sendMessage([
            'chat_id' => '1327706165',
            'text' => json_encode($res, JSON_PRETTY_PRINT)
        ]);

        $this->Telegram->sendMessage([
            'chat_id' => '1327706165',
            'text' => json_encode($request->all(), JSON_PRETTY_PRINT)
        ]);
    }
}
