<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Keyboard\Keyboard;

class TelegramApiController extends Controller
{
    private Api $Telegram;

    public function __construct()
    {
        $this->Telegram = new Api();
    }

    /**
     * @throws TelegramSDKException
     */
    public function handle(Request $request)
    {
        $data = $request->all();
        $this->Telegram->sendMessage([
            'chat_id' => '1327706165',
            'text' => json_encode($data, JSON_PRETTY_PRINT)
        ]);

        $message = $data['message'];
        if (empty($message)) return;
        if ($message['text'] === '/start') {
            $keyboard = Keyboard::make([
                'keyboard' => [
                    [
                        [
                            'text' => 'Предоставить номер телефона',
                            'request_contact' => true
                        ]
                    ]
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ]);

            $this->Telegram->sendMessage([
                'chat_id' => '1327706165',
                'text' => 'Пожалуйста, поделитесь вашим номером телефона',
                'reply_markup' => $keyboard
            ]);
        }
    }
}
