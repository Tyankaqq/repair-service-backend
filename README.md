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
