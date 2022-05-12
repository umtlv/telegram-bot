<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\User;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\Update;

class TelegramApiController extends Controller
{
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
     * @throws TelegramSDKException
     */
    public function handle(Request $request): Response
    {
        $data = new Update($request->all());
        $this->Telegram->sendMessage([
            'chat_id' => '1327706165',
            'text' => json_encode($data->all(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        ]);
        $this->Message = $data->getMessage();
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

        if (!$this->User->is_registered) {
            $this->registration();
            return $this->success();
        }

        switch ($this->Message->text) {
            case '/profile':
                $this->showProfile();
                break;
            case '/change_city':
                $this->changeCity();
                break;
            default:
                $this->defaultMessage();
        }

        return $this->success();
    }

    private function showProfile()
    {
    }

    private function changeCity()
    {
    }

    /**
     * @throws TelegramSDKException
     */
    private function defaultMessage()
    {
        $this->reply("Выберите действие:\n\n/profile - Показать профиль\n\n/edit_city - Изменить город\n/edit_full_name - Изменить ФИО\n/edit_birthday - Изменить дату рождения\n/edit_nickname - Изменить никнейм");
    }

    /**
     * @throws TelegramSDKException
     */
    private function registration()
    {
        // Заполняем номер телефона
        if (is_null($this->User['msisdn'])) {
            if (!empty($this->Message->contact->phone_number)) {
                $this->User->msisdn = $this->Message->contact->phone_number;
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
            if (!preg_match('/(\d{2}\/\d{2}\/\d{4})/', $this->Text)) {
                $this->reply("Вы ввели дату в неправильном формате. Отправьте, пожалуйста, дату вашего рождения в формате - ДД/ММ/ГГГГ.");
            } else {
                $date = DateTime::createFromFormat('d/m/Y', $this->Text);
                $errors = DateTime::getLastErrors();
                if ($errors['warning_count'] === 0) {
                    $this->User->birth_date = $date->format('Y-m-d');
                    $this->User->saveQuietly();
                    $this->reply("Отправьте, пожалуйста, никнейм. Заполнить латинскими буквами.");
                } else $this->reply("Вы ввели неправильную дату.");
            }
            return;
        }

        if (is_null($this->User['nickname'])) {
            if (!preg_match('/^[a-z0-9]+$/i', $this->Text)) {
                $this->reply("Никнейм может содержать только латинские буквы");
            } else {
                $nickname = User::where('nickname', $this->Text)->first();
                if (empty($nickname)) {
                    $this->User->nickname = $this->Text;
                    $this->User->is_registered = true;
                    $this->User->saveQuietly();

                    $this->reply("Спасибо, Вы успешно зарегистрированы.");
                    $this->defaultMessage();
                } else $this->reply("Данный никнейм занят.");
            }
        }
    }

    /**
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
