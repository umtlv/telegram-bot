<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\User;
use App\Traits\Verification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\Update;

class TelegramApiController extends Controller
{
    use Verification;

    private Api $Telegram;
    private User|null $User;
    private Collection $Message;
    private int $SenderId;
    private string|null $Text;

    public function __construct()
    {
        $this->Telegram = new Api();
    }

    /**
     * Обработчик входящих запросов от Телеграм
     * @throws TelegramSDKException
     */
    public function handle(Request $request): Response
    {
        $data = new Update($request->all());
        $this->Message = $data->getMessage();
        $this->Telegram->sendMessage([
            'chat_id' => '1327706165',
            'text' => json_encode($data->getMessage(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        ]);
        $this->SenderId = $this->Message->from->id;
        $this->Text = $this->Message->text;
        $this->User = User::where('telegram_user_id', $this->SenderId)->first();

        // Если пользователя нету в БД
        if (empty($this->User)) {
            $user = new User();
            $user->telegram_user_id = $this->SenderId;
            $user->saveQuietly();

            $this->reply("Для начала работы, необходимо зарегистрироваться.");
            $this->requestPhoneNumber();
            return $this->success();
        }

        // Если пользователь еще не зарегистрирован
        if (!$this->User->is_registered) {
            $this->registration();
            return $this->success();
        }

        // Если происходит изменение
        if ($this->User->step) {
            $this->change();
            return $this->success();
        }

        switch ($this->Message->text) {
            case '/profile':
                $this->showProfile();
                break;
            case '/edit_city':
                $this->editCity();
                break;
            case '/edit_full_name':
                $this->editFullName();
                break;
            case '/edit_birthday':
                $this->editBirthday();
                break;
            case '/edit_nickname':
                $this->editNickName();
                break;
            case '/help':
                $this->defaultMessage();
                break;
        }

        return $this->success();
    }

    /**
     * Изменение данных
     * @throws TelegramSDKException
     */
    private function change()
    {
        if ($this->Text === '/cancel') {
            $this->User->step = null;
            $this->User->saveQuietly();
            $this->reply("Изменение отменено.", Keyboard::make(['remove_keyboard' => true]));
        }

        switch ($this->User->step) {
            case 1:
                $city = City::where('title', $this->Text)->first();
                if (empty($city)) $this->requestCity(true);
                else {
                    $this->User->city_id = $city->id;
                    $this->User->step = null;
                    $this->User->saveQuietly();

                    $this->reply("Город успешно изменен.", Keyboard::make(['remove_keyboard' => true]));
                }
                break;
            case 2:
                $this->User->full_name = $this->Text;
                $this->User->step = null;
                $this->User->saveQuietly();

                $this->reply("Ваше имя и фамилия изменены.");
                break;
            case 3:
                try {
                    $date = self::checkDate($this->Text);
                    $this->User->birth_date = $date;
                    $this->User->step = null;
                    $this->User->saveQuietly();

                    $this->reply("Дата вашего рождения успешно изменена.");
                } catch (Exception $e) {
                    $this->reply($e->getMessage());
                }
                break;
            case 4:
                try {
                    self::checkNickName($this->Text);
                    $this->User->nickname = $this->Text;
                    $this->User->step = null;
                    $this->User->saveQuietly();

                    $this->reply("Никнейм успешно изменен.");
                } catch (Exception $e) {
                    $this->reply($e->getMessage());
                }
                break;
        }
    }

    /**
     * Сообщение от отменен изменения
     * @throws TelegramSDKException
     */
    private function cancelEdition()
    {
        $this->reply("Для отмены нажмите /cancel");
    }

    /**
     * Показать профиль
     * @throws TelegramSDKException
     */
    private function showProfile()
    {
        $city = City::where('id', $this->User->city_id)->first();
        $birthDate = date('d.m.Y', strtotime($this->User['birth_date']));
        $this->reply(
            "<b>{$this->User->full_name}</b>, ваш профиль: \n\nВаш город: <b>$city->title</b>\nДата вашего рождения: <b>$birthDate</b>\nВаш никнейм: <b>{$this->User->nickname}</b>",
            Keyboard::make(['remove_keyboard' => true])
        );
    }

    /**
     * Изменить город
     * @throws TelegramSDKException
     */
    private function editCity()
    {
        $this->User->step = 1;
        $this->User->saveQuietly();

        $this->requestCity();
        $this->cancelEdition();
    }

    /**
     * Изменить ФИО
     * @throws TelegramSDKException
     */
    private function editFullName()
    {
        $this->User->step = 2;
        $this->User->saveQuietly();

        $this->reply("Отправьте, пожалуйста, ваше имя и фамилия.");
        $this->cancelEdition();
    }

    /**
     * Изменить дату рождения
     * @throws TelegramSDKException
     */
    private function editBirthday()
    {
        $this->User->step = 3;
        $this->User->saveQuietly();

        $this->reply("Отправьте, пожалуйста, дату вашего рождения в формате - ДД/ММ/ГГГГ.");
        $this->cancelEdition();
    }

    /**
     * Изменить никнейм
     * @throws TelegramSDKException
     */
    private function editNickName()
    {
        $this->User->step = 4;
        $this->User->saveQuietly();

        $this->reply("Отправьте, пожалуйста, никнейм. Заполнить латинскими буквами.");
        $this->cancelEdition();
    }

    /**
     * Стандартное сообщение
     * @throws TelegramSDKException
     */
    private function defaultMessage()
    {
        $keyboard = Keyboard::make([
            'keyboard' => [
                [
                    '/profile',
                ],
                [
                    '/edit_city',
                    '/edit_full_name',
                ],
                [
                    '/edit_birthday',
                    '/edit_nickname'
                ]
            ],
            'one_time_keyboard' => true,
            'resize_keyboard' => true,
        ]);
        $this->reply(
            "Выберите действие:\n\n/profile - Показать профиль\n\n/edit_city - Изменить город\n/edit_full_name - Изменить ФИО\n/edit_birthday - Изменить дату рождения\n/edit_nickname - Изменить никнейм",
            $keyboard
        );
    }

    /**
     * Регистрация
     * @throws TelegramSDKException
     */
    private function registration()
    {
        // Заполняем номер телефона
        if (is_null($this->User['msisdn'])) {
            if ($this->Message->has('contact')) {
                $this->User->msisdn = $this->Message->get('contact')->get('phone_number');
                $this->User->saveQuietly();
                $this->requestCity();
            } else $this->requestPhoneNumber();
            return;
        }

        // Заполняем город
        if (is_null($this->User['city_id'])) {
            $city = City::where('title', $this->Text)->first();
            if (empty($city)) $this->requestCity(true);
            else {
                $this->User->city_id = $city->id;
                $this->User->saveQuietly();
                $this->reply("Отправьте, пожалуйста, ваше имя и фамилия.", Keyboard::make([
                    'remove_keyboard' => true
                ]));
            }
            return;
        }

        // Заполняем имя и фамилия
        if (is_null($this->User['full_name'])) {
            $this->User->full_name = $this->Text;
            $this->User->saveQuietly();
            $this->reply("Отправьте, пожалуйста, дату вашего рождения в формате - ДД/ММ/ГГГГ.");
            return;
        }

        // Заполняем дату рождения
        if (is_null($this->User['birth_date'])) {
            try {
                $date = self::checkDate($this->Text);
                $this->User->birth_date = $date;
                $this->User->saveQuietly();
                $this->reply("Отправьте, пожалуйста, никнейм. Заполнить латинскими буквами.");
            } catch (Exception $e) {
                $this->reply($e->getMessage());
            }
            return;
        }

        // Заполняем никнейм
        if (is_null($this->User['nickname'])) {
            try {
                self::checkNickName($this->Text);
                $this->User->nickname = $this->Text;
                $this->User->is_registered = true;
                $this->User->saveQuietly();

                $this->reply("Спасибо, вы успешно зарегистрированы.");
                $this->defaultMessage();
            } catch (Exception $e) {
                $this->reply($e->getMessage());
            }
        }
    }

    /**
     * Запросить номер телефона
     * @throws TelegramSDKException
     */
    private function requestPhoneNumber()
    {
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

    /**
     * Запросить город
     * @throws TelegramSDKException
     */
    private function requestCity($wrong = false)
    {
        $text = "Пожалуйста, выберите ваш город из списка.";
        if ($wrong) $text = "Данный город не действителен.  " . $text;
        $cities = City::select(['title'])->get()->pluck('title');
        $keyboard = Keyboard::make([
            'keyboard' => [$cities],
            'one_time_keyboard' => true,
            'resize_keyboard' => true,
        ]);
        $this->reply($text, $keyboard);
    }

    /**
     * Ответить
     * @throws TelegramSDKException
     */
    private function reply(string $text, Keyboard|null $markup = null)
    {
        $params = [
            'chat_id' => $this->SenderId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        if (!is_null($markup)) $params['reply_markup'] = $markup;
        $this->Telegram->sendMessage($params);
    }
}
