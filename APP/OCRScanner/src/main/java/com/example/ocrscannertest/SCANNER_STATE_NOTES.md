
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

Добавь в конец SCANNER_STATE_NOTES.md такой блок.

---

## 2026-04-24 — State machine test после патча с callback registry

### Что изменили

Вместо одного callback для CameraX теперь используется registry callbacks по owner:

```kotlin
private val cameraReleaseCallbacks = linkedMapOf<String, () -> Unit>()
private val cameraRestoreCallbacks = linkedMapOf<String, () -> Unit>()

Цель:

    не затирать callback между разными camera screen;

    видеть в логах, кто реально зарегистрировал release/restore;

    при hard scan отпускать CameraX перед vendor scanner HAL.

Текущий лог

При нажатии hard scan key:

SCAN_QR_DIAG: dedicated_scan_key_passthrough dispatch keyCode=289 action=0
HS_BOOTSTRAP: state IDLE -> HARDWARE_SCAN_MODE reason=hard_key_289
HS_BOOTSTRAP: camera release callbacks count=0 owners=[] reason=hard_key_289
HS_BOOTSTRAP: camera release requested reason=hard_key_289
SCAN_QR_DIAG: dedicated_scan_key_passthrough onKeyDown keyCode=289

Через watchdog:

HS_BOOTSTRAP: no BARCODE_SEND after hardware trigger; restore camera keyCode=289
HS_BOOTSTRAP: schedule camera restore reason=no_barcode_after_hard_key_289 delay=1800ms
HS_BOOTSTRAP: state HARDWARE_SCAN_MODE -> CAMERA_MODE reason=no_barcode_after_hard_key_289
HS_BOOTSTRAP: camera restore callbacks count=0 owners=[] reason=no_barcode_after_hard_key_289
HS_BOOTSTRAP: camera restore requested reason=no_barcode_after_hard_key_289

Главный вывод

State machine работает, но callback registry пустой:

camera release callbacks count=0 owners=[]
camera restore callbacks count=0 owners=[]

Это означает:

    enterHardwareScanMode() вызывается;

    hard key 289 до приложения доходит;

    watchdog работает;

    переходы IDLE -> HARDWARE_SCAN_MODE -> CAMERA_MODE работают;

    но CameraX release/restore callback не зарегистрирован в момент нажатия hard scan key.

То есть текущая проблема уже не в dispatchKeyEvent, не в BARCODE_SEND, не в dedup и не в DecodeService.

Проблема сейчас в том, что активный экран камеры не регистрирует callback через:

setScannerCameraCallbacks(...)

или регистрирует, но потом callback снимается через onDispose.
Почему laser scan не стартует

При hard scan нет реального освобождения камеры:

camera release callbacks count=0 owners=[]

Значит vendor scanner HAL всё ещё может видеть занятый camera resource или некорректный foreground/camera state.

Ожидаемый рабочий лог должен быть примерно такой:

HS_BOOTSTRAP: scanner camera callbacks registered owner=BarcodeScanScreen releaseCount=1 restoreCount=1
...
HS_BOOTSTRAP: state CAMERA_MODE -> HARDWARE_SCAN_MODE reason=hard_key_289
HS_BOOTSTRAP: camera release callbacks count=1 owners=[BarcodeScanScreen] reason=hard_key_289
HS_BOOTSTRAP: camera release invoke owner=BarcodeScanScreen reason=hard_key_289
HS_BOOTSTRAP: CameraX released by scanner state machine

Сейчас этого нет.
Что нужно проверить в Kotlin

Нужно найти все вызовы:

setScannerCameraCallbacks

и привести их к новой сигнатуре:

setScannerCameraCallbacks(
    owner = "BarcodeScanScreen",
    releaseCamera = { ... },
    restoreCamera = { ... }
)

и снятие callback:

setScannerCameraCallbacks(
    owner = "BarcodeScanScreen",
    releaseCamera = null,
    restoreCamera = null
)

Отдельно для OCR screen:

setScannerCameraCallbacks(
    owner = "OcrScanScreen",
    releaseCamera = { ... },
    restoreCamera = { ... }
)

и:

setScannerCameraCallbacks(
    owner = "OcrScanScreen",
    releaseCamera = null,
    restoreCamera = null
)

Следующий точный шаг

Перед дальнейшими тестами laser scan надо добиться в логах регистрации callback:

scanner camera callbacks registered owner=BarcodeScanScreen releaseCount=1 restoreCount=1

или:

scanner camera callbacks registered owner=OcrScanScreen releaseCount=1 restoreCount=1

Если при открытом camera scan этого лога нет — state machine нечем управлять.
Текущий статус

    build после замены cameraRestoreCallback должен собираться;

    hard key обрабатывается;

    state machine включается;

    BARCODE_SEND не приходит;

    OemScanDemo может показывать No decoded message available, но это не рабочий decode success;

    главный blocker: callback registry пустой в момент hard scan.

Вывод: сначала исправить регистрацию setScannerCameraCallbacks(...) в BarcodeScanScreen и OcrScanScreen, потом снова тестировать hard scan.


По текущим логам диагноз жёсткий: **мы построили state machine, но она не управляет CameraX, потому что callback не зарегистрирован**.


---

## 2026-04-24 — Vendor APK reverse findings

### Что проверили

С устройства были вытянуты vendor APK:

```bash
adb pull /system/app/dcsservice/dcsservice.apk
adb pull /system/app/scanservice/scanservice.apk
adb pull /system/app/scantool/scantool.apk

Пути на устройстве:

/system/app/dcsservice/dcsservice.apk
/system/app/scanservice/scanservice.apk
/system/app/scantool/scantool.apk

После декомпиляции через jadx были проверены:

/tmp/hs_reverse/jadx/dcsservice
/tmp/hs_reverse/jadx/scanservice
/tmp/hs_reverse/jadx/scantool

Главный вывод

Рабочий laser scan генерирует не прямой вызов com.hs.scanservice/.DecodeService.

Основная рабочая цепочка такая:

hardware key
→ com.hs.dcsservice / OemScanDemo / DcsService
→ waitForDecodeTwo()
→ decode success
→ com.android.hs.action.BARCODE_SEND
→ OCRScanner получает broadcast

То есть dcsservice является главным producer результата скана.

scanservice больше похож на secondary/proxy service и consumer события BARCODE_SEND.
dcsservice.apk

Ключевой файл:

/tmp/hs_reverse/jadx/dcsservice/sources/com/hs/dcsservice/DcsService.java

В нём найдена рабочая логика:

waitForDecodeTwo(...)
decode success
intent.setAction("com.android.hs.action.BARCODE_SEND")
intent.putExtra("scanner_result", ...)
intent.putExtra("scanner_result_byte", ...)
sendBroadcast(intent, "com.honeywell.decode.permission.DECODE")

Подтверждение из runtime log:

OemScanDemo: waitForDecodeTwo returned
OemScanDemo: decode success!
ActivityManager: Sending non-protected broadcast com.android.hs.action.BARCODE_SEND
SCAN_QR_DIAG: intent_path action=com.android.hs.action.BARCODE_SEND ... extracted=...
SCAN_QR_DIAG: dispatchHardwareScan source=hardware_intent ...

Также приходит дополнительный action:

com.android.giec.action.BARCODE_FOCAL

Вывод:

    DcsService реально запускает decode loop;

    DcsService формирует BARCODE_SEND;

    основные поля результата: scanner_result, scanner_result_byte;

    permission: com.honeywell.decode.permission.DECODE.

scanservice.apk

Ключевой файл:

/tmp/hs_reverse/jadx/scanservice/sources/com/hs/scanservice/DecodeService.java

Найдено:

registerReceiver(... "com.android.hs.action.BARCODE_SEND" ...)

Вывод:

    DecodeService слушает результат от dcsservice;

    прямой startService/startForegroundService для него не является командой “начать сканирование”;

    ранее подтверждённый ANR после startForegroundService() делает этот путь запрещённым.

Запрещено использовать как рабочий путь:

startForegroundService(com.hs.scanservice/.DecodeService)
startService(com.hs.scanservice/.DecodeService)

bindService можно оставлять только как диагностику, но он не запускает laser scan.
scantool.apk

Ключевые файлы:

/tmp/hs_reverse/jadx/scantool/sources/com/hs/scantool/MainActivity.java
/tmp/hs_reverse/jadx/scantool/sources/com/hs/scantool/NewMainActivity.java

Найдено:

registerReceiver(... "com.android.hs.action.BARCODE_SEND" ...)

Вывод:

    vendor ScanTool тоже работает как consumer BARCODE_SEND;

    значит для нашего приложения правильный путь — слушать broadcast output;

    не надо имитировать ScanTool через невидимое окно как основное решение.

Vendor output settings

В dcsservice найдены настройки:

isFocus
isBroadcast
BroadcastTransmission
BroadcastBarcodeString
BroadcastBarcode

В res/xml/scan_setting.xml найдены:

isBroadcast
isFocus
scan_switch
BroadcastBarcode
BroadcastBarcodeString
BroadcastTransmission

Вывод:

    vendor stack поддерживает focus output и broadcast output;

    broadcast output подтверждён логами;

    OCRScanner должен продолжать слушать:

com.android.hs.action.BARCODE_SEND
com.android.giec.action.BARCODE_FOCAL

2026-04-24 — Текущий точный диагноз

По актуальным логам state machine уже работает:

SCAN_QR_DIAG: dedicated_scan_key_passthrough dispatch keyCode=289 action=0
HS_BOOTSTRAP: state IDLE -> HARDWARE_SCAN_MODE reason=hard_key_289

Но registry callbacks пустой:

HS_BOOTSTRAP: camera release callbacks count=0 owners=[] reason=hard_key_289
HS_BOOTSTRAP: camera restore callbacks count=0 owners=[] reason=no_barcode_after_hard_key_289

Это означает:

    hard key 289 до приложения доходит;

    enterHardwareScanMode() вызывается;

    watchdog работает;

    переходы IDLE/CAMERA_MODE -> HARDWARE_SCAN_MODE -> CAMERA_MODE работают;

    но активный CameraX screen не зарегистрировал release/restore callback;

    state machine нечем освобождать CameraX.

Главный blocker сейчас:

setScannerCameraCallbacks(...) не зарегистрирован активным BarcodeScanScreen/OcrScanScreen

или callback регистрируется, но снимается через onDispose раньше, чем нажимается hard scan.
Почему laser scan не стартует после camera scan

Вероятная причина — конкуренция за camera/imager resource:

CameraX активен / некорректно освобождён
→ vendor scanner HAL не получает ресурс
→ hard key виден
→ touchDown/touchUp есть
→ но нет decode success
→ нет BARCODE_SEND

Ожидаемый рабочий лог должен выглядеть так:

HS_BOOTSTRAP: scanner camera callbacks registered owner=BarcodeScanScreen releaseCount=1 restoreCount=1
HS_BOOTSTRAP: state CAMERA_MODE -> HARDWARE_SCAN_MODE reason=hard_key_289
HS_BOOTSTRAP: camera release callbacks count=1 owners=[BarcodeScanScreen] reason=hard_key_289
HS_BOOTSTRAP: camera release invoke owner=BarcodeScanScreen reason=hard_key_289
HS_BOOTSTRAP: CameraX released by scanner state machine
OemScanDemo: decode success!
SCAN_QR_DIAG: intent_path action=com.android.hs.action.BARCODE_SEND ... extracted=...
HS_BOOTSTRAP: schedule camera restore reason=barcode_received_com.android.hs.action.BARCODE_SEND delay=900ms
HS_BOOTSTRAP: camera restore invoke owner=BarcodeScanScreen reason=...

Сейчас вместо этого:

camera release callbacks count=0 owners=[]

Следующий точный шаг

В MainActivity.kt проверить все вызовы:

grep -n "setScannerCameraCallbacks" MainActivity.kt

Новая сигнатура должна использовать owner:

setScannerCameraCallbacks(
    owner = "BarcodeScanScreen",
    releaseCamera = {
        liveAnalysis?.clearAnalyzer()
        liveAnalysis = null
        liveScanner?.close()
        liveScanner = null
        boundCameraProvider?.unbindAll()
        camera = null
        Log.i("HS_BOOTSTRAP", "CameraX released owner=BarcodeScanScreen")
    },
    restoreCamera = {
        cameraRestoreTick++
        Log.i("HS_BOOTSTRAP", "CameraX restore tick=$cameraRestoreTick owner=BarcodeScanScreen")
    }
)

Снятие callback:

setScannerCameraCallbacks(
    owner = "BarcodeScanScreen",
    releaseCamera = null,
    restoreCamera = null
)

Для OCR screen аналогично:

setScannerCameraCallbacks(
    owner = "OcrScanScreen",
    releaseCamera = {
        liveAnalysis?.clearAnalyzer()
        liveAnalysis = null
        boundCameraProvider?.unbindAll()
        camera = null
        Log.i("HS_BOOTSTRAP", "CameraX released owner=OcrScanScreen")
    },
    restoreCamera = {
        cameraRestoreTick++
        Log.i("HS_BOOTSTRAP", "CameraX restore tick=$cameraRestoreTick owner=OcrScanScreen")
    }
)

Снятие:

setScannerCameraCallbacks(
    owner = "OcrScanScreen",
    releaseCamera = null,
    restoreCamera = null
)

Перед дальнейшими laser scan тестами нужно добиться одного из логов:

HS_BOOTSTRAP: scanner camera callbacks registered owner=BarcodeScanScreen releaseCount=1 restoreCount=1

или:

HS_BOOTSTRAP: scanner camera callbacks registered owner=OcrScanScreen releaseCount=1 restoreCount=1

Без этого state machine не управляет CameraX.
Что больше не считать перспективным

Не тратить время на:

startForegroundService(com.hs.scanservice/.DecodeService)
startService(com.hs.scanservice/.DecodeService)
бесконечные DecodeService warmup loops
невидимые окна
FLAG_NOT_FOCUSABLE как постоянное решение
SettingActivity kick как основное решение

Эти подходы уже проверялись и не дают стабильного решения.
Короткий итог

На текущем этапе диагноз такой:

vendor broadcast path рабочий;
dcsservice является producer BARCODE_SEND;
hard key до приложения доходит;
state machine включается;
но CameraX callbacks не зарегистрированы;
поэтому CameraX не освобождается перед hard scan.

Следующая задача — не трогать vendor services, а исправить регистрацию setScannerCameraCallbacks(...) в активных camera composable screens.


---

## 2026-04-25 — Вывод после reboot test, cam-scan и hard laser scan

### Что проверили

После reboot Android laser scan внутри `OCRScanner` заработал при отправке vendor action:

```text
com.hs.dcsservice.action
action=open
noAddScanApp=1
permission=com.honeywell.decode.permission.DECODE

В рабочем логе видно:

HS_BOOTSTRAP: sent com.hs.dcsservice.action open noAddScanApp=1 reason=hard_key_289
OemScanDemo: onKeepGoingCallback
OemScanDemo: g_bKeepGoing = true

Это подтвердило, что noAddScanApp=1 реально влияет на vendor stack и не даёт сразу добавить наше приложение в noScanApp.
Что происходит после cam-scan

После входа через camera scan vendor stack останавливает decode thread:

OemScanDemo: onPause
OemScanDemo: stop scanning++
OemScanDemo: stop scanning--
OemScanDemo: onStop++
OemScanDemo: !!!!! DECODE THREAD HAS STOPPED RUNNING !!!!!
OemScanDemo: m_Decoder null++
OemScanDemo: m_Decoder null--

После этого vendor stack видит наше приложение как foreground:

D/xc: 当前应用:com.example.ocrscannertest
D/xc: 当前应用ShortClassName:.MainActivity

Дальше он сам снова делает open:

OemScanDemo: open
OemScanDemo: Configure preferences based on user settings...
OemScanDemo: ***** DECODE THREAD IS RUNNING *****

Но после этого hard laser scan внутри нашего приложения всё равно может не дать decode success и BARCODE_SEND.
Вывод по blacklist

В vendor code найдена простая blacklist-логика:

String noScanApp = MMKVUtils.getString("noScanApp", "");
String foregroundTaskPackageName = getAppPackageName(getApplicationContext());

if (!foregroundTaskPackageName.contains("com.mediatek.camera")
        && !noScanApp.contains(foregroundTaskPackageName)) {
    ...
}

Также найдена логика сохранения short class:

String ShortClassName = NewFloatWindowService.getShortClassName(...)
MMKVUtils.put("noScanShortClass", ShortClassName);

Runtime подтверждает, что после camera flow vendor stack видит:

com.example.ocrscannertest
com.example.ocrscannertest.MainActivity

Текущий вывод:

    проблема не только в noScanApp;

    вероятно, после cam-scan vendor stack меняет внутреннее состояние по foreground package / short class / scan state;

    noAddScanApp=1 помогает после reboot, но не всегда восстанавливает laser scan после camera flow.

Почему одного open недостаточно

Текущий код отправляет:

action=open
noAddScanApp=1

При этом в логах после нерабочего состояния видно:

HS_BOOTSTRAP: sent com.hs.dcsservice.action open noAddScanApp=1 reason=hard_key_289

Но дальше нет стабильного:

OemScanDemo: decode success
com.android.hs.action.BARCODE_SEND

Значит open поднимает/будит vendor stack, но не всегда сбрасывает его состояние после camera scan.
Следующая рабочая гипотеза

Перед аппаратным laser scan нужно делать controlled reset vendor scanner state:

pause → stop → open

Не через startService / startForegroundService, а через тот же публичный broadcast endpoint:

com.hs.dcsservice.action
permission=com.honeywell.decode.permission.DECODE

Рекомендуемая последовательность:

sendHsDcsAction("pause", reason)
delay 80 ms
sendHsDcsAction("stop", reason)
delay 220 ms
sendHsDcsAction("open", reason, noAddScanApp = true)

Ожидаемый лог:

HS_BOOTSTRAP: sent com.hs.dcsservice.action action=pause noAddScanApp=1 reason=hard_key_289
HS_BOOTSTRAP: sent com.hs.dcsservice.action action=stop noAddScanApp=1 reason=hard_key_289
HS_BOOTSTRAP: sent com.hs.dcsservice.action action=open noAddScanApp=1 reason=hard_key_289
OemScanDemo: open
OemScanDemo: Configure preferences based on user settings...
OemScanDemo: ***** DECODE THREAD IS RUNNING *****

После этого при успешном scan должно появиться:

OemScanDemo: waitForDecodeTwo returned
OemScanDemo: decode success!
ActivityManager: Sending non-protected broadcast com.android.hs.action.BARCODE_SEND
SCAN_QR_DIAG: intent_path action=com.android.hs.action.BARCODE_SEND
SCAN_QR_DIAG: dispatchHardwareScan source=hardware_intent

Время восстановления

По текущим логам vendor open после camera flow занимает примерно 1 секунду до:

OemScanDemo: ***** DECODE THREAD IS RUNNING *****

Поэтому pause → stop → open не должен быть долгим как перезапуск приложения. Это не запуск UI APK, а сброс vendor scanner service state через broadcast commands.

Ориентировочно:

pause/stop/open command chain: 300–500 ms
vendor open + configure: около 1 сек

Для UX это приемлемо, если делать reset только перед hard scan или после camera scan, а не постоянно в фоне.
Запрещённые пути

Не использовать:

startForegroundService(com.hs.scanservice/.DecodeService)
startService(com.hs.scanservice/.DecodeService)

Причина уже подтверждена ранее:

ANR in com.hs.scanservice
Context.startForegroundService() did not then call Service.startForeground()

Также не использовать как основное решение:

FLAG_NOT_FOCUSABLE
ручной запуск vendor UI
имитацию com.hs.touchDown / com.hs.touchUp из нашего приложения

Текущий технический вывод

На данный момент наиболее вероятная причина:

После CameraX / cam-scan vendor scanner stack остаётся в состоянии, где DcsService/OemScanDemo формально открыт,
но trigger/decode state для foreground com.example.ocrscannertest не восстановлен.

Поэтому следующий патч должен быть не про Kotlin key handling, а про vendor state reset:

hard key 289
→ enterHardwareScanMode()
→ release CameraX callbacks, если они есть
→ send pause
→ send stop
→ send open noAddScanApp=1
→ ждать BARCODE_SEND
→ если BARCODE_SEND пришёл, restore camera позже
→ если не пришёл, watchdog restore camera

Главная цель следующего теста:

    После reboot проверить, что laser scan всё ещё работает.

    Войти через cam-scan.

    Нажать hard scan.

    Убедиться, что в логах есть pause → stop → open.

    Проверить, вернулся ли decode success / BARCODE_SEND.




---

## 2026-04-25 — Промежуточный итог перед паузой

### Главная цель

Добиться стабильной работы двух режимов в `OCRScanner`:

1. CameraX / MLKit scan через камеру приложения.
2. Hardware laser scan через vendor scanner stack.

Критично: после входа через camera QR / после OCR / после любого CameraX-сценария физическая кнопка сканера должна снова запускать laser scan без ребута Android и без ручного открытия ScannerTools.

---

## Что уже точно выяснено

### 1. Hardware key физически работает

Физическая кнопка даёт события:

```text
com.hs.touchDown
com.hs.touchUp
SCAN_QR_DIAG: dedicated_scan_key_passthrough dispatch keyCode=289 action=0
SCAN_QR_DIAG: dedicated_scan_key_passthrough dispatch keyCode=289 action=1

Значит проблема не в кнопке и не в том, что Android вообще не видит событие.
2. Vendor scanner stack физически работает

После ребута Android или в рабочем состоянии vendor stack показывает:

OemScanDemo: onKeepGoingCallback
OemScanDemo: g_bKeepGoing = true
OemScanDemo: waitForDecodeTwo returned

При успешном decode должен приходить broadcast:

com.android.hs.action.BARCODE_SEND

Иногда также используется:

com.android.giec.action.BARCODE_FOCAL

3. Наша app имеет обычный app UID, не system UID

Текущее состояние com.example.ocrscannertest:

userId=10159
codePath=/data/app/...
flags=[ DEBUGGABLE HAS_CODE ALLOW_CLEAR_USER_DATA ALLOW_BACKUP ]

Vendor services:

com.hs.dcsservice userId=1000 sharedUser=android.uid.system
com.hs.scanservice userId=1000 sharedUser=android.uid.system

Вывод:

    наша app не system app;

    она не uid=1000;

    она не имеет sharedUser=android.uid.system;

    простое добавление permissions в manifest не делает её системной;

    установка в /system/priv-app без platform/system подписи тоже не даст uid=1000.

4. Permissions частично выданы, но это не решило trigger/decode

В manifest добавлены:

<uses-permission android:name="com.hs.scanbutton.permission.DECODE" />
<uses-permission android:name="com.honeywell.decode.permission.DECODE" />
<uses-permission android:name="com.hs.decode.permission.DECODE" />

Фактически granted:

com.hs.scanbutton.permission.DECODE: granted=true
com.honeywell.decode.permission.DECODE: granted=true

Но:

com.hs.decode.permission.DECODE

как отдельный package/declared permission не найден через dumpsys package.

Вывод:

    com.hs.scanbutton.permission.DECODE и com.honeywell.decode.permission.DECODE реально существуют;

    com.hs.decode.permission.DECODE в текущей системе не найден как declared permission;

    проблема не только в manifest permissions.

Что показал reverse vendor APK

Были декомпилированы:

/system/app/dcsservice/dcsservice.apk
/system/app/scanservice/scanservice.apk
/system/app/scantool/scantool.apk

Основные пути jadx:

/tmp/hs_reverse/jadx/dcsservice
/tmp/hs_reverse/jadx/scanservice
/tmp/hs_reverse/jadx/scantool

dcsservice — главный producer результата скана

Ключевой файл:

/tmp/hs_reverse/jadx/dcsservice/sources/com/hs/dcsservice/DcsService.java

Найдена логика успешного результата:

intent.setAction("com.android.hs.action.BARCODE_SEND");
intent.putExtra("scanner_result", ...);
intent.putExtra("scanner_result_byte", ...);
sendBroadcast(intent, "com.honeywell.decode.permission.DECODE");

Вывод:

    com.hs.dcsservice / OemScanDemo — главный источник результата;

    именно он делает BARCODE_SEND;

    наша app должна продолжать слушать BARCODE_SEND.

scanservice — не команда “начать скан”

Ключевой файл:

/tmp/hs_reverse/jadx/scanservice/sources/com/hs/scanservice/DecodeService.java

Найдено, что DecodeService слушает:

com.android.hs.action.BARCODE_SEND

Ранее тестировали:

startForegroundService(...)
startService(...)
bindService(...)

Вывод:

    bindService технически проходит, но scan не запускает;

    startForegroundService() вызывает ANR, потому что vendor service не вызывает startForeground();

    startService() / startForegroundService() для com.hs.scanservice/.DecodeService запрещены как рабочее решение.

Blacklist / noScanApp

В NewFloatWindowService.java найдена логика:

String noScanApp = MMKVUtils.getString("noScanApp", "");
String foregroundTaskPackageName = getAppPackageName(...);

if (!foregroundTaskPackageName.contains("com.mediatek.camera")
    && !noScanApp.contains(foregroundTaskPackageName)) {
    ...
}

Также найдена логика добавления foreground app в blacklist:

MMKVUtils.put("noScanApp", foregroundTaskPackageName);
MMKVUtils.put("noScanShortClass", ShortClassName);

И очистка:

MMKVUtils.put("noScanApp", "");
MMKVUtils.put("noScanShortClass", "");

Важный момент:

Intent action = "com.hs.scanservice.getsetting.action"

В receiver есть логика, которая может очищать noScanApp/noScanShortClass, если foreground activity/class изменился.

Текущий вывод:

    blacklist-гипотеза остаётся вероятной;

    после camera scan vendor stack мог добавить com.example.ocrscannertest в noScanApp;

    после ребута Android laser scan снова работал, что похоже на сброс vendor state;

    прямого доступа к MMKV storage нет, потому что app data vendor services недоступна без root.

Почему “сделать app системной” пока не основной путь

Чтобы app реально стала system-level как vendor services, недостаточно просто поставить APK в /system/app или /system/priv-app.

Нужно одновременно:

1. root/remount или кастомная прошивка;
2. установка APK в /system/priv-app или /system/app;
3. подпись platform/system certificate;
4. возможно sharedUserId="android.uid.system";
5. совпадение подписи с системным ключом прошивки.

На текущем устройстве:

adb root
adbd cannot run as root in production builds

Значит простого adb root/remount пути нет.

Вывод:

    без root или кастомной прошивки системной app мы её сейчас не сделаем;

    даже priv-app без platform signature не станет uid=1000;

    это крайний путь, не текущий быстрый фикс.

Текущий рабочий вектор: broadcast output mode

В vendor коде найдены настройки:

isFocus
isBroadcast
BroadcastTransmission
BroadcastBarcodeString
BroadcastBarcode

В DcsService.java логика такая:

if (MMKVUtils.getBoolean("isBroadcast", false)
    && !MMKVUtils.getString("BroadcastTransmission", "").isEmpty()) {

    intent.setAction(MMKVUtils.getString("BroadcastTransmission", ""));

    if (!MMKVUtils.getString("BroadcastBarcodeString", "").isEmpty()) {
        intent.putExtra(
            MMKVUtils.getString("BroadcastBarcodeString", ""),
            intent.getStringExtra("original_result")
        );
    }

    if (!MMKVUtils.getString("BroadcastBarcode", "").isEmpty()) {
        intent.putExtra(
            MMKVUtils.getString("BroadcastBarcode", ""),
            intent.getByteArrayExtra("scanner_result_byte")
        );
    }

    sendBroadcast(intent);
}

Важное уточнение

BroadcastBarcode=raw — это не фиксированная vendor-настройка.

Это означает:

BroadcastBarcode = имя extra для byte array

Например можно задать:

BroadcastBarcode = raw

Тогда vendor broadcast отправит byte array extra с именем raw.

Для нас важнее строковый вариант:

BroadcastTransmission = com.example.ocrscannertest.SCAN_RESULT
BroadcastBarcodeString = data
BroadcastBarcode = raw

Минимально достаточно:

BroadcastTransmission = com.example.ocrscannertest.SCAN_RESULT
BroadcastBarcodeString = data

Следующий точный план
Шаг 1. Добавить custom broadcast action в OCRScanner

В MainActivity.kt в scanIntentActions добавить первым:

"com.example.ocrscannertest.SCAN_RESULT",

Итог:

private val scanIntentActions = listOf(
    "com.example.ocrscannertest.SCAN_RESULT",
    "com.honeywell.decode.intent.action.SCAN_RESULT",
    "com.datalogic.decodewedge.decode_action",
    "android.intent.ACTION_DECODE_DATA",
    "nlscan.action.SCANNER_RESULT",
    "com.sunmi.scanner.ACTION_DATA_CODE_RECEIVED",
    "com.android.hs.action.BARCODE_SEND",
    "com.android.giec.action.BARCODE_FOCAL"
)

Шаг 2. Расширить список extra keys

В extractScanPayload() добавить явные ключи:

"original_result",
"scanner_result",
"scanner_result_byte",
"raw",

data уже есть, но оставить его первым.

Пример блока keys:

val keys = listOf(
    "data",
    "original_result",
    "scanner_result",
    "scanner_result_byte",
    "raw",
    "scanData",
    "SCAN_BARCODE1",
    "com.symbol.datawedge.data_string",
    "barcode_string",
    "decode_data",
    "code",
    "value",
    "BARCODE",
    "barcode",
    "result",
    "text",
    "message",
    "decode_rslt",
    "scannerdata",
    "barcodeData",
    "dataBytes",
    "aimId",
    "charset"
)

Шаг 3. Через ScannerTools найти broadcast output settings

Нужно найти экран, где задаются:

isBroadcast
BroadcastTransmission
BroadcastBarcodeString
BroadcastBarcode

В UI это может называться не дословно. Возможные названия:

Broadcast
Broadcast Output
Output Mode
Data Output
Transmission
Intent Action
Barcode String
Barcode Data
Focus / Broadcast
Keyboard / Broadcast

Поставить:

Output mode = Broadcast
BroadcastTransmission = com.example.ocrscannertest.SCAN_RESULT
BroadcastBarcodeString = data
BroadcastBarcode = raw

Если BroadcastBarcode не видно — пропустить. Главное:

BroadcastTransmission
BroadcastBarcodeString

Шаг 4. Проверить logcat

Команда:

adb logcat -c
adb logcat -v time | grep -i -E "SCAN_QR_DIAG|intent_path|SCAN_RESULT|BARCODE_SEND|BARCODE_FOCAL|original_result|scanner_result|scanner_result_byte|data|raw"

Ожидаемый успешный лог:

SCAN_QR_DIAG: intent_path action=com.example.ocrscannertest.SCAN_RESULT extrasKeys=[data] extracted=...
SCAN_QR_DIAG: dispatchHardwareScan source=hardware_intent raw='...' normalized='...'

Или старый vendor action:

SCAN_QR_DIAG: intent_path action=com.android.hs.action.BARCODE_SEND extrasKeys=[scanner_result, scanner_result_byte] extracted=...

Что НЕ делать дальше

Не возвращаться к этим путям как к основному решению:

startForegroundService(com.hs.scanservice/.DecodeService)
startService(com.hs.scanservice/.DecodeService)
FLAG_NOT_FOCUSABLE
ручной запуск vendor SplashActivity как постоянный workaround
бесконечные warmup/retry/restart циклы

Причины:

    startForegroundService ломает vendor service через ANR;

    bindService не запускает decode;

    window focus hacks ломают UX/WebView;

    vendor UI kick нестабилен;

    retries не меняют trigger policy.

Альтернативный путь, если broadcast mode не найдём в UI

Если ScannerTools UI не даёт выставить BroadcastTransmission/BroadcastBarcodeString, следующий шаг — исследовать vendor setProperties.

В reverse найден action:

com.hs.dcsservice.action
action = setProperties
properties = Serializable Map<String, Object>

Из scanservice/h.java:

intent2.setAction("com.hs.dcsservice.action");
intent2.putExtra("action", "setProperties");
intent2.putExtra("properties", (Serializable) map);
sendBroadcast(intent2, "com.honeywell.decode.permission.DECODE");

Потенциально можно отправить map:

mapOf(
    "isBroadcast" to true,
    "isFocus" to false,
    "BroadcastTransmission" to "com.example.ocrscannertest.SCAN_RESULT",
    "BroadcastBarcodeString" to "data",
    "BroadcastBarcode" to "raw"
)

Но это следующий тест, не первый. Сначала пробуем UI.
Текущий статус на паузу

    Hardware key виден.

    Vendor scanner физически живой.

    После ребута laser scan может оживать.

    После camera scan / QR login laser scan снова может отваливаться.

    noScanApp blacklist-гипотеза остаётся сильной.

    Manifest permissions частично granted, но не решают всё.

    System app путь сейчас небыстрый, потому что нет adb root и нет platform signature.

    Главный следующий путь: перевести vendor output в Broadcast mode и принимать custom action в OCRScanner.


Перед дорогой главное запомнить коротко: **не трогаем больше startService/foregroundService, ищем Broadcast output в ScannerTools, а в app добавляем `com.example.ocrscannertest.SCAN_RESULT` и extra keys.**



Что найдено

Вендорский “blacklist” называется:

noScanApp

И хранится через:

MMKVUtils

Ключевой кусок:

const-string v4, "noScanApp"
...
MMKVUtils->getString("noScanApp", "")
...
foregroundTaskPackageName
...
MMKVUtils->put("noScanApp", foregroundTaskPackageName)

То есть vendor реально делает:

если foreground app не launcher и noAddScanApp != 1
→ добавить foreground package в noScanApp

А потом при нажатии кнопки проверяет:

если noScanApp содержит foregroundTaskPackageName
→ не делать ACTION_DOWN/ACTION_UP scan

Почему наш noAddScanApp=1 не лечит полностью

Наш Kotlin сейчас делает:

putExtra("noAddScanApp", 1)

Это только не даёт добавить app заново.

Но если com.example.ocrscannertest уже попал в:

noScanApp

то noAddScanApp=1 его оттуда не удаляет.

А BootCompleteReceiver на старте Android делает именно очистку:

const-string v0, "noScanApp"
const-string v1, ""
MMKVUtils->put("noScanApp", "")

То есть после ребута лазер мог оживать именно потому, что noScanApp очищался.

