<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\User;
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

        $message = $data['message'];
        if (empty($message)) return;

        $sender = $message['from']['id'];
        $user = User::where('telegram_user_id', $sender)->first();

        // Если пользователя нету в списке и нажата команда - /start
        if (empty($user) && $message['text'] === '/start') {
            $keyboard = Keyboard::make([
                'keyboard' => [
                    [
                        [
                            'text' => 'Предоставить номер телефона',
                            'request_contact' => true
                        ]
                    ]
                ],
                'one_time_keyboard' => true,
                'resize_keyboard' => true,
            ]);
            $this->Telegram->sendMessage([
                'chat_id' => $sender,
                'text' => "Для начала работы, необходимо зарегистрироваться.<br><br>Пожалуйста, поделитесь вашим номером телефона.",
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard
            ]);

            $user = new User();
            $user->telegram_user_id = $sender;
            $user->saveQuietly();
        }

        // Заполняем номер телефона
        if (is_null($user['msisdn'])) {
            if (!empty($message['contact'])) {
                $user->msisdn = $message['contact']['phone_number'];
                $user->saveQuietly();

                $cities = City::select(['title'])->get()->pluck('title');
                $keyboard = Keyboard::make([
                   'keyboard' => $cities,
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true,
                ]);
                $this->Telegram->sendMessage([
                    'chat_id' => $sender,
                    'text' => "Пожалуйста, выберите ваш город.",
                    'reply_markup' => $keyboard
                ]);
            } else {
                $keyboard = Keyboard::make([
                    'keyboard' => [
                        [
                            [
                                'text' => 'Предоставить номер телефона',
                                'request_contact' => true
                            ]
                        ]
                    ],
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true,
                ]);
                $this->Telegram->sendMessage([
                    'chat_id' => $sender,
                    'text' => "Пожалуйста, поделитесь вашим номером телефона.",
                    'reply_markup' => $keyboard
                ]);
            }
        }

        // Заполняем город
        if (is_null($user['city_id'])) {
            $city = City::where('title', $message['text'])->first();
            if (empty($city)) {
                $cities = City::select(['title'])->get()->pluck('title');
                $keyboard = Keyboard::make([
                    'keyboard' => [$cities],
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true,
                ]);
                $this->Telegram->sendMessage([
                    'chat_id' => $sender,
                    'text' => "Данный город не действителен. Пожалуйста, выберите из списка.",
                    'reply_markup' => $keyboard
                ]);
            } else {
                $user->city_id = $city->id;
                $user->saveQuietly();

                $this->Telegram->sendMessage([
                    'chat_id' => $sender,
                    'text' => "Отправьте, пожалуйста, ваше имя и фамилия.",
                    'reply_markup' => Keyboard::make([
                        'remove_keyboard' => true
                    ])
                ]);
            }
        }

        $this->Telegram->sendMessage([
            'chat_id' => '1327706165',
            'text' => json_encode($data, JSON_PRETTY_PRINT),
            'reply_markup' => Keyboard::make([
                'remove_keyboard' => true
            ])
        ]);
    }
}
