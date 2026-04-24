
# Scanner State Notes

## Цель

Сделать так, чтобы в приложении `OCRScanner` стабильно работали оба сценария:

1. Camera scan / OCR через камеру приложения.
2. Hardware laser scan через физическую кнопку сканера.

Нужно, чтобы после использования камеры laser scan не отваливался и чтобы для восстановления не приходилось открывать vendor scanner tools или выходить на рабочий стол.

---

## Текущая проблема

На устройстве используется vendor scanner stack:

- `com.hs.scanservice`
- `com.hs.dcsservice`
- `com.hs.scantool`
- `OemScanDemo`
- `ScanDrv`

После запуска приложения или после camera scan физическая кнопка сканера иногда перестаёт запускать луч.

В логах при нажатии hard scan key внутри приложения видно только:

```text
D/xc: com.hs.touchDown
D/xc: com.hs.touchUp
```

Но нет нормальной цепочки:

```text
com.hs.scanservice
com.hs.dcsservice.action
OemScanDemo onKeepGoingCallback
decode success
BARCODE_SEND
```

На рабочем столе hard scan key работает нормально.

---

## Рабочий сценарий: launcher / рабочий стол

Когда приложение не foreground, а пользователь находится на рабочем столе, hard scan key запускает vendor stack корректно.

Типовой лог:

```text
com.hs.touchDown
com.hs.scanservice
com.hs.dcsservice.action
OemScanDemo: onKeepGoingCallback
OemScanDemo: g_bKeepGoing = true
com.hs.touchUp
OemScanDemo: decode success
com.android.hs.action.BARCODE_SEND
```

Вывод:

- железная кнопка физически исправна;
- scanner engine физически исправен;
- vendor stack умеет отдавать результат через `BARCODE_SEND`.

---

## Нерабочий сценарий: наше приложение foreground

Когда foreground app — `com.example.ocrscannertest`, hard scan key до системы доходит, но vendor decode не стартует.

Типовой лог:

```text
com.hs.touchDown
SCAN_QR_DIAG: wedge_key keyCode=289 unicode=0 buffer=''
com.hs.touchUp
```

После теста passthrough:

```text
com.hs.touchDown
SCAN_QR_DIAG: dedicated_scan_key_passthrough keyCode=289 action=0
com.hs.touchUp
SCAN_QR_DIAG: dedicated_scan_key_passthrough keyCode=289 action=1
```

После `return false`:

```text
com.hs.touchDown
SCAN_QR_DIAG: dedicated_scan_key_return_false keyCode=289 action=0
com.hs.touchUp
SCAN_QR_DIAG: dedicated_scan_key_return_false keyCode=289 action=1
```

Вывод:

- проблема не в том, что Kotlin полностью “съел” кнопку;
- системный слой `xc` видит `touchDown/touchUp`;
- vendor stack не продолжает цепочку до `OemScanDemo`.

---

## Найденные vendor-компоненты через dumpsys

### `com.hs.dcsservice`

Найдено:

```text
Activity:
com.hs.dcsservice/com.hs.scanbutton.SplashActivity

Activity action:
com.hs.dcsservice.action -> com.hs.dcsservice/.SettingActivity

Permission:
com.hs.scanbutton.permission.DECODE
```

`SplashActivity` — vendor scanner tools UI. Его запуск может разбудить сканер, но это плохой workaround:

- всплывает чужой экран;
- ломается UX;
- нужен возврат в наше приложение;
- иногда нужно ждать 3–5 секунд;
- после camera scan всё равно нестабильно.

`SettingActivity` открывается через action:

```text
com.hs.dcsservice.action
```

Вероятно, это экран/обработчик настроек vendor scanner stack.

### `com.hs.scanservice`

Найдено:

```text
Service:
com.hs.scanservice/.DecodeService

Service action:
com.hs.scanservice.action

Required permission:
com.honeywell.decode.permission.DECODE
```

Это реальный decode service. Он находится не в `com.hs.dcsservice`, а в `com.hs.scanservice`.

---

## Проверенный warmup через `DecodeService`

Пробовали запускать и bind-ить:

```kotlin
Intent("com.hs.scanservice.action")
```

и явно:

```kotlin
ComponentName("com.hs.scanservice", "com.hs.scanservice.DecodeService")
```

В логах это срабатывало:

```text
HS_BOOTSTRAP: warmup explicit startService ok
HS_BOOTSTRAP: warmup action startService ok
HS_BOOTSTRAP: warmup bindService result=true
HS_BOOTSTRAP: warmup bindService connected name=ComponentInfo{com.hs.scanservice/com.hs.scanservice.DecodeService}
HS_BOOTSTRAP: warmup unbindService ok
```

Но после этого hard scan key внутри приложения всё равно не запускает луч.

Вывод:

- `DecodeService` можно поднять;
- `bindService` технически проходит;
- это не равно “начать сканирование”;
- trigger routing не доходит до active decode.

---

## Что НЕ сработало

### 1. Запуск vendor `SplashActivity`

Плюс:

- иногда оживляет scanner stack.

Минусы:

- открывает vendor UI;
- нужен возврат в приложение;
- может стартовать несколько раз;
- после camera scan нестабильно;
- плохой UX.

### 2. `SCANRESTART`

Ранее проверялось. Стабильного результата не дало.

### 3. Broadcast `touchDown/touchUp` из приложения

Программная отправка:

```kotlin
sendBroadcast(Intent("com.hs.touchDown"))
sendBroadcast(Intent("com.hs.touchUp"))
```

не дала эквивалента реальному нажатию кнопки.

На рабочем столе реальный hard key запускает цепочку:

```text
scanservice -> dcsservice -> OemScanDemo
```

А программный broadcast из нашего app этого стабильно не делает.

### 4. `dispatchKeyEvent` passthrough

Пробовали для keyCode `289/290`:

```kotlin
return super.dispatchKeyEvent(event)
```

Результат: не помогло.

### 5. `dispatchKeyEvent return false`

Пробовали:

```kotlin
return false
```

Результат: не помогло. Событие логируется, но vendor decode всё равно не стартует.

### 6. `FLAG_ALT_FOCUSABLE_IM`

Пробовали:

```kotlin
window.setFlags(
    WindowManager.LayoutParams.FLAG_ALT_FOCUSABLE_IM,
    WindowManager.LayoutParams.FLAG_ALT_FOCUSABLE_IM
)
```

Результат: не помогло.

### 7. `FLAG_NOT_FOCUSABLE`

Пробовали:

```kotlin
window.addFlags(WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE)
```

Результат:

- меняет focus/input-поведение;
- приложение/WebView начинает вести себя плохо;
- были ANR / app isn't responding;
- как решение не подходит.

---

## Вывод по гипотезе keyboard/input

Гипотеза была такая: наше приложение перехватывает hard scan key как клавиатурное событие, поэтому vendor scanner stack не получает кнопку.

Проверка показала:

- `dispatchKeyEvent` действительно видит keyCode `289/290`;
- даже при `return super` и `return false` vendor stack не запускается;
- системный `xc` всё равно логирует `com.hs.touchDown/touchUp`.

Значит проблема глубже, чем Kotlin key handling.

Скорее всего, vendor scanner stack принимает решение запускать decode в зависимости от foreground window/focus/app mode. Когда foreground — launcher, decode запускается. Когда foreground — наше fullscreen приложение, decode не запускается.

---

## Вывод по `dumpsys window`

В состоянии нашего приложения foreground видно:

```text
Window com.example.ocrscannertest/com.example.ocrscannertest.MainActivity
type=BASE_APPLICATION
```

Также видно много overlay-окон:

```text
package=com.hs.dcsservice
type=PHONE
type=APPLICATION_OVERLAY
```

На рабочем столе scanner работает, но когда наше приложение foreground — не работает.

Вывод:

- наличие overlay-окон `com.hs.dcsservice` само по себе не гарантирует работу hard scan;
- отличие именно в foreground/focused `BASE_APPLICATION`.

---

## ADB root недоступен

Проверка:

```bash
adb root
```

Результат:

```text
adbd cannot run as root in production builds
```

Вывод:

- устройство собрано как production build;
- `adbd` не может работать от root;
- private data каталогов системных приложений недоступны через обычный `adb shell`.

Недоступные каталоги:

```text
/data/user/0/com.hs.dcsservice
/data/user/0/com.hs.scanservice
```

Это не доказывает, что там нет конфигов или логов. Это только означает, что shell не имеет прав их читать.

Дальше диагностику ведем через:

```text
dumpsys package
dumpsys window windows
logcat
am startservice
am broadcast
settings get/list
cmd package
```

---

## 2026-04-24: выводы после тестов `DecodeService`

### Что проверили

В актуальном коде пробовали прямой warmup через:

```text
com.hs.scanservice/.DecodeService
Action: com.hs.scanservice.action
Permission: com.honeywell.decode.permission.DECODE
```

Сначала использовался `startForegroundService/startService` плюс `bindService`.

Лог показывал:

```text
DecodeService startService ok
DecodeService bindService result=true
DecodeService bind connected
DecodeService unbind ok
```

Но после этого появлялся критичный системный ANR vendor-сервиса:

```text
ANR in com.hs.scanservice
Reason: Context.startForegroundService() did not then call Service.startForeground()
Killing com.hs.scanservice
Scheduling restart of crashed service com.hs.scanservice/.DecodeService
```

### Вывод по `startForegroundService/startService`

`startForegroundService()` для `com.hs.scanservice/.DecodeService` использовать нельзя.

Причина:

- Android ожидает, что сервис после `startForegroundService()` вызовет `startForeground()`;
- vendor `DecodeService` этого не делает;
- система считает это нарушением foreground service contract;
- появляется ANR;
- `com.hs.scanservice` убивается и перезапускается.

Это ухудшает состояние scanner stack и не оживляет laser scan.

### Правильная правка

Для `DecodeService` был оставлен только `bindService` без `startService/startForegroundService`.

После правки лог стал безопаснее:

```text
DecodeService startService skipped; bindService only reason=no_barcode_after_trigger
DecodeService bindService result=true reason=no_barcode_after_trigger
DecodeService bind connected reason=no_barcode_after_trigger name=ComponentInfo{com.hs.scanservice/com.hs.scanservice.DecodeService}
DecodeService unbind ok reason=no_barcode_after_trigger
```

ANR больше не появляется.

### Но `bindService` проблему не решил

После успешного `bindService` всё равно нет цепочки:

```text
com.hs.dcsservice.action
OemScanDemo: open
OemScanDemo: ***** DECODE THREAD IS RUNNING *****
BARCODE_SEND
```

При нажатии hard scan key внутри нашего приложения по-прежнему видно:

```text
com.hs.touchDown
SCAN_QR_DIAG: dedicated_scan_key_passthrough dispatch keyCode=289 action=0
SCAN_QR_DIAG: dedicated_scan_key_passthrough onKeyDown keyCode=289
com.hs.touchUp
SCAN_QR_DIAG: dedicated_scan_key_passthrough dispatch keyCode=289 action=1
SCAN_QR_DIAG: dedicated_scan_key_passthrough onKeyUp keyCode=289
```

Затем watchdog видит отсутствие barcode:

```text
no BARCODE_SEND after hardware trigger, request warmup keyCode=289
```

`bindService` проходит, но laser beam / decode не стартует.

### Главный вывод

`DecodeService` — не кнопка “начать сканирование”.

Факт успешного подключения к сервису означает только то, что binder endpoint доступен. Без знания внутреннего AIDL/API или нужного vendor-команда он не инициирует decode.

Текущий путь через `DecodeService` безопасен только как диагностика, но не как рабочее решение.

---

## 2026-04-24: вывод по focus-pulse / invisible-window идее

Была проверена идея кратковременно менять фокус/оконное состояние нашего приложения, чтобы vendor overlay `com.hs.dcsservice` получил возможность обработать hard scan key.

Лог показывал:

```text
HS_BOOTSTRAP: scanner focus pulse enabled reason=hard_key_289
HS_BOOTSTRAP: scanner focus pulse restored reason=hard_key_289
```

Но при этом laser scan всё равно не активировался.

Вывод:

- временный focus pulse сам по себе не восстанавливает trigger routing;
- наличие overlay-окон `com.hs.dcsservice` не равно активному decode state;
- попытки ломать фокус окна опасны для UX и WebView.

---

## 2026-04-24: следующий перспективный путь

Так как:

- `DecodeService bindService` работает, но decode не стартует;
- `startForegroundService/startService` вызывает ANR vendor-сервиса;
- touchDown/touchUp и dispatch passthrough не помогают;
- launcher/desktop state запускает цепочку `OemScanDemo` корректно;

следующий тест должен идти не через `DecodeService`, а через публичный entrypoint `com.hs.dcsservice.action`.

Найденный endpoint:

```text
Activity action:
com.hs.dcsservice.action -> com.hs.dcsservice/.SettingActivity
```

Почему это интересно:

- в рабочих логах вокруг `com.hs.dcsservice.action` появляется `OemScanDemo open`;
- именно `OemScanDemo` показывает `DECODE THREAD IS RUNNING`;
- `DecodeService` без `OemScanDemo` не даёт результата.

### Экспериментальная идея

Сделать controlled kick через `SettingActivity`:

```kotlin
val intent = Intent("com.hs.dcsservice.action").apply {
    setClassName("com.hs.dcsservice", "com.hs.dcsservice.SettingActivity")
    addCategory(Intent.CATEGORY_DEFAULT)
    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
    addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP)
    addFlags(Intent.FLAG_ACTIVITY_NO_ANIMATION)
}
startActivity(intent)
```

Затем через `Handler` вернуть наше приложение на foreground через launch intent:

```kotlin
packageManager.getLaunchIntentForPackage(packageName)
```

с флагами:

```text
FLAG_ACTIVITY_NEW_TASK
FLAG_ACTIVITY_SINGLE_TOP
FLAG_ACTIVITY_CLEAR_TOP
FLAG_ACTIVITY_REORDER_TO_FRONT
```

### Важные ограничения

Не использовать:

```text
FLAG_NOT_FOCUSABLE
startForegroundService(com.hs.scanservice/.DecodeService)
startService(com.hs.scanservice/.DecodeService)
```

`bindService` можно оставить только как диагностический fallback, но не считать его решением.

---

## Текущий статус

- Hardware key физически работает.
- Scanner engine физически работает.
- `BARCODE_SEND` приходит, когда vendor stack реально декодирует.
- Внутри нашего foreground app trigger не доходит до active decode.
- `DecodeService` найден.
- Permission `com.honeywell.decode.permission.DECODE` добавлен в manifest.
- `bindService` к `DecodeService` работает.
- `startForegroundService/startService` для `DecodeService` вызывает ANR и запрещён к использованию.
- Проблема не решена warmup-ом.
- Следующий шаг — тестировать `com.hs.dcsservice.action -> SettingActivity` как controlled vendor kick или искать официальный API/настройку scan-over-foreground.

---

## Что искать дальше

Ключевые направления поиска:

```text
foreground scan
scan trigger in foreground app
keyboard wedge mode
broadcast output mode
scan output mode
focal mode
barcode focal
hardware trigger mode
decode trigger mode
Honeywell AidcManager trigger
com.hs.scanservice DecodeService API
com.hs.dcsservice BARCODE_SEND
```

Ключевые package/action/permission:

```text
com.hs.scanservice
com.hs.scanservice/.DecodeService
com.hs.scanservice.action
com.honeywell.decode.permission.DECODE

com.hs.dcsservice
com.hs.scanbutton.SplashActivity
com.hs.dcsservice.action
com.hs.scanbutton.permission.DECODE

com.android.hs.action.BARCODE_SEND
com.android.giec.action.BARCODE_FOCAL
com.hs.touchDown
com.hs.touchUp
```

---

## Важный вывод для разработки

Не делать ставку на бесконечные warmup/watchdog/restart циклы.

Причина: warmup может поднять service, но не меняет trigger routing policy.

Правильное решение должно быть одно из:

1. Найти vendor API для direct trigger/decode.
2. Найти настройку vendor stack: broadcast mode / foreground app scan allowed.
3. Использовать официальную Honeywell AIDC API, если она реально поддерживается на этом устройстве.
4. Если vendor stack закрытый и не даёт API — использовать controlled vendor UI kick как workaround.

## 2026-04-24 — SettingActivity / ScanTools kick test

### Hypothesis

Try to start vendor `com.hs.dcsservice/.SettingActivity` from our app after missing `BARCODE_SEND`, expecting vendor UI to reinitialize scanner stack.

### Implementation

`requestScannerVendorBootstrap()` was changed to start:

```kotlin
Intent("com.hs.dcsservice.action").apply {
    setClassName("com.hs.dcsservice", "com.hs.dcsservice.SettingActivity")
    addCategory(Intent.CATEGORY_DEFAULT)
    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
    addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP)
    addFlags(Intent.FLAG_ACTIVITY_NO_ANIMATION)
    addFlags(Intent.FLAG_ACTIVITY_REORDER_TO_FRONT)
}