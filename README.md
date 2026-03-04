# Repair Service API

## Запуск проекта

```bash
# 1. Запустить MySQL + phpMyAdmin
docker-compose up -d

# 2. Установить зависимости
composer install

# 3. Скопировать .env
cp .env.example .env
php artisan key:generate

# 4. Миграции + сиды
php artisan migrate --seed

# 5. Запустить сервер
php artisan serve
```

## Тестовые пользователи

| Роль       | Email                      | Пароль   |
|------------|----------------------------|----------|
| Диспетчер  | dispatcher@repair.local    | secret123 |
| Мастер 1   | master1@repair.local       | secret123 |
| Мастер 2   | master2@repair.local       | secret123 |

---

## Проверка защиты от гонки (Race Condition)

Метод "Взять в работу" использует `DB::transaction()` + `lockForUpdate()`.
Это означает: первый запрос блокирует строку в БД, второй ждёт,
затем видит статус уже `in_progress` и получает `409 Conflict`.

### Шаг 1 — Подготовка

```bash
# Получить токен диспетчера
TOKEN_D=$(curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"dispatcher@repair.local","password":"password"}' \
  | python -c "import sys,json; print(json.load(sys.stdin)['token'])")

# Создать заявку
curl -s -X POST http://localhost:8000/api/requests \
  -H "Content-Type: application/json" \
  -d '{"clientName":"Тест","phone":"79001234567","address":"ул. Ленина 1","problemText":"Проблема"}'

# Назначить мастера на заявку id=1
curl -s -X PATCH http://localhost:8000/api/requests/1/assign \
  -H "Authorization: Bearer $TOKEN_D" \
  -H "Content-Type: application/json" \
  -d '{"assignedTo":2}'
```

### Шаг 2 — Получить токен мастера

```bash
TOKEN_M=$(curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"master1@repair.local","password":"password"}' \
  | python -c "import sys,json; print(json.load(sys.stdin)['token'])")
```

### Шаг 3 — Два одновременных запроса

```bash
curl -s -X PATCH http://localhost:8000/api/requests/1/take \
  -H "Authorization: Bearer $TOKEN_M" & \
curl -s -X PATCH http://localhost:8000/api/requests/1/take \
  -H "Authorization: Bearer $TOKEN_M" &
wait
```

### Ожидаемый результат

```json
{"id":1,"status":"in_progress",...}
{"message":"Request is already in status: in_progress"}
```

Один запрос вернёт `200 OK`, второй — `409 Conflict`.

### Альтернатива — PowerShell (Windows)

```powershell
$token = "твой_токен_мастера"

$job1 = Start-Job {
    Invoke-RestMethod -Method Patch `
        -Uri "http://localhost:8000/api/requests/1/take" `
        -Headers @{ Authorization = "Bearer $using:token" }
}

$job2 = Start-Job {
    Invoke-RestMethod -Method Patch `
        -Uri "http://localhost:8000/api/requests/1/take" `
        -Headers @{ Authorization = "Bearer $using:token" }
}

Receive-Job $job1 -Wait
Receive-Job $job2 -Wait
```
Роли и доступ
Публичный пользователь:

Может открыть /create и отправить заявку на ремонт.

Диспетчер:

Заходит в систему по тестовому логину.

Доступ к кабинету диспетчера (страница /dispatcher):

Просмотр всех заявок.

Фильтрация по статусу.

Назначение заявок мастерам.

Отмена заявок (кроме завершённых).

Мастер:

Заходит по логину мастера.

Доступ к кабинету мастера (страница /master):

Список назначенных ему заявок.

Взятие заявки в работу.

Завершение заявки.

Как проверить защиту от «гонки»
Сценарий демонстрации защиты при взятии заявки в работу:

Залогиниться под мастером (например, master1@repair.local / secret123).

В кабинете мастера найти заявку со статусом assigned.

Открыть два окна (или два инкогнито) браузера под тем же мастером.

В обоих окнах открыть одну и ту же заявку.

Одновременно нажать кнопку «Взять в работу» в обоих окнах.

Ожидаемый результат:

В одном окне заявка успешно переводится в статус in_progress.

Во втором окне API возвращает ошибку 409 (конфликт), а в UI показывается сообщение о том, что заявка уже находится в другом статусе и не может быть взята в работу повторно.

Тестирование
Запуск автотестов backend
bash
cd backend
php artisan test
В проекте есть минимум два feature‑теста:

тест создания заявки через API;

тест корректной смены статуса и обработки конфликтной ситуации при попытке взятия заявки в работу.

text

Если хочешь, можешь сразу подставить реальные URL фронта (`/login`, `/dispatcher`, `/master`, `/create`) и конкретное название проекта.

***

## 2. Черновик DECISIONS.md

```md
# DECISIONS

Ключевые архитектурные и технические решения проекта.

1. **Разделение на API и SPA**

   Backend реализован как REST API на Laravel, frontend — как одностраничное приложение на React + Vite.  
   Это позволяет независимо разворачивать клиент и сервер и упрощает развитие интерфейса.

2. **Ролевая модель (dispatcher / master)**

   В системе две основные роли: диспетчер и мастер.  
   Для каждой роли сделаны отдельные кабинеты и проверки доступа на уровне маршрутов и API.

3. **Аутентификация по токену**

   Для аутентификации используется токен (Personal Access Token Laravel / Sanctum‑подход).  
   При логине backend возвращает токен и данные пользователя, фронтенд сохраняет токен и передаёт его в заголовке `Authorization: Bearer`.

4. **Публичная форма создания заявки**

   Страница `/create` доступна без авторизации, чтобы любой пользователь мог оставить заявку на ремонт.  
   Все остальные маршруты (кабинеты диспетчера и мастера) защищены и требуют авторизации с соответствующей ролью.

5. **Модель статусов заявок**

   У заявки есть ограниченный набор статусов: `new`, `assigned`, `in_progress`, `done`, `canceled`.  
   Переходы между статусами контролируются на уровне backend (например, нельзя завершить уже отменённую заявку).

6. **Защита от гонки при взятии заявки в работу**

   Метод, который переводит заявку в статус `in_progress`, обёрнут в транзакцию с блокировкой строки (пессимистическая блокировка).  
   Это гарантирует, что только один мастер сможет взять заявку в работу, а параллельный запрос получит ошибку с кодом `409`.

7. **Явная поддержка CORS и JSON‑API**

   Клиент и сервер могут работать на разных origin (например, разные порты локально).  
   Включён CORS на backend, а клиент всегда запрашивает JSON (`Accept: application/json`), чтобы API не возвращал HTML/редиректы.
3. Минимум два автотеста (Feature)
Ниже — примеры, их можно адаптировать под твои точные роуты и модели.

tests/Feature/CreateRepairRequestTest.php
php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\RepairRequest;

class CreateRepairRequestTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_repair_request_via_public_api()
    {
        $payload = [
            'clientName'  => 'Иван Иванов',
            'phone'       => '+7 900 000-00-00',
            'address'     => 'Калининград, ул. Тестовая, д. 1',
            'problemText' => 'Не работает кондиционер',
        ];

        $response = $this->postJson('/api/requests', $payload);

        $response
            ->assertStatus(201)
            ->assertJsonFragment([
                'clientName'  => 'Иван Иванов',
                'status'      => 'new',
            ]);

        $this->assertDatabaseHas('repair_requests', [
            'clientName'  => 'Иван Иванов',
            'phone'       => '+7 900 000-00-00',
            'status'      => 'new',
        ]);
    }
}
tests/Feature/TakeInProgressTest.php
php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\RepairRequest;

class TakeInProgressTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function master_can_take_assigned_request_in_progress()
    {
        $master = User::factory()->create([
            'role' => 'master',
        ]);

        $request = RepairRequest::factory()->create([
            'status'     => 'assigned',
            'assignedTo' => $master->id,
        ]);

        $this->actingAs($master);

        $response = $this->postJson("/api/requests/{$request->id}/take-in-progress");

        $response
            ->assertStatus(200)
            ->assertJsonFragment([
                'status' => 'in_progress',
            ]);

        $this->assertDatabaseHas('repair_requests', [
            'id'     => $request->id,
            'status' => 'in_progress',
        ]);
    }

    /** @test */
    public function second_take_in_progress_returns_conflict()
    {
        $master = User::factory()->create([
            'role' => 'master',
        ]);

        $request = RepairRequest::factory()->create([
            'status'     => 'assigned',
            'assignedTo' => $master->id,
        ]);

        $this->actingAs($master);

        // первый успешный перевод в in_progress
        $this->postJson("/api/requests/{$request->id}/take-in-progress")
            ->assertStatus(200);

        // повторная попытка должна упасть с 409
        $this->postJson("/api/requests/{$request->id}/take-in-progress")
            ->assertStatus(409);
    }
}
