<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\User;
use DateTime;
use Illuminate\Http\Request;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Keyboard\Keyboard;

class TelegramApiController extends Controller
{
    private Api $Telegram;
    private int $Sender;

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

        $this->Sender = $message['from']['id'];
        $user = User::where('telegram_user_id', $this->Sender)->first();

        // Если пользователя нету в списке и нажата команда
        if (empty($user)) {
            $user = new User();
            $user->telegram_user_id = $this->Sender;
            $user->saveQuietly();

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
            $this->reply("Для начала работы, необходимо зарегистрироваться.<br><br>Пожалуйста, поделитесь вашим номером телефона.", $keyboard);
            return;
        }

        // Заполняем номер телефона
        if (is_null($user['msisdn'])) {
            if (!empty($message['contact'])) {
                $user->msisdn = $message['contact']['phone_number'];
                $user->saveQuietly();

                $cities = City::select(['title'])->get()->pluck('title');
                $keyboard = Keyboard::make([
                    'keyboard' => [$cities],
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true,
                ]);
                $this->reply("Пожалуйста, выберите ваш город.", $keyboard);
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
                $this->reply("Пожалуйста, поделитесь вашим номером телефона.", $keyboard);
            }
            return;
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
                $this->reply("Данный город не действителен. Пожалуйста, выберите из списка.", $keyboard);
            } else {
                $user->city_id = $city->id;
                $user->saveQuietly();
                $this->reply("Отправьте, пожалуйста, ваше имя и фамилия.", Keyboard::make([
                    'remove_keyboard' => true
                ]));
            }
            return;
        }

        // Заполняем имя и фамилия
        if (is_null($user['full_name'])) {
            $user->full_name = $message['text'];
            $user->saveQuietly();
            $this->reply("Отправьте, пожалуйста, дату ваше рождения в формате - ДД/ММ/ГГГГ.");
            return;
        }

        // Заполняем дату рождения
        if (is_null($user['birth_date'])) {
            if (!preg_match('/(\d{2}\/\d{2}\/\d{4})/', $message['text'])) {
                $this->reply("Вы ввели дату в непривильном формате. Отправьте, пожалуйста, дату ваше рождения в формате - ДД/ММ/ГГГГ.");
            } else {
                $date = DateTime::createFromFormat('d/m/Y', $message['text']);
                $errors = DateTime::getLastErrors();
                if ($errors['warning_count'] === 0) {
                    $user->birth_date = $date->format('Y-m-d');
                    $user->saveQuietly();
                    $this->reply("Отправьте, пожалуйста, никнейм. Заполнить латинскими буквами.");
                } else {
                    $this->reply("Вы ввели дату в непривильном формате. Отправьте, пожалуйста, дату ваше рождения в формате - ДД/ММ/ГГГГ.");
                }
            }
        }
    }

    /**
     * @throws TelegramSDKException
     */
    private function reply(string $text, Keyboard|null $markup = null)
    {
        $params = [
            'chat_id' => $this->Sender,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        if (!is_null($markup)) $params['reply_markup'] = $markup;
        $this->Telegram->sendMessage($params);
    }
}
