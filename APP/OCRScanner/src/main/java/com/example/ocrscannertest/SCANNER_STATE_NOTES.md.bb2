
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

Но нет нормальной цепочки:

com.hs.scanservice
com.hs.dcsservice.action
OemScanDemo onKeepGoingCallback
decode success
BARCODE_SEND

На рабочем столе hard scan key работает нормально.
Рабочий сценарий: рабочий стол / launcher

Когда приложение не foreground, а пользователь находится на рабочем столе, hard scan key запускает vendor stack корректно.

Типовой лог:

com.hs.touchDown
com.hs.scanservice
com.hs.dcsservice.action
OemScanDemo: onKeepGoingCallback
OemScanDemo: g_bKeepGoing = true
com.hs.touchUp
OemScanDemo: decode success
com.android.hs.action.BARCODE_SEND

Вывод: железная кнопка и vendor scanner service физически исправны.
Нерабочий сценарий: наше приложение foreground

Когда foreground app — com.example.ocrscannertest, hard scan key до системы доходит, но vendor decode не стартует.

Типовой лог:

com.hs.touchDown
SCAN_QR_DIAG: wedge_key keyCode=289 unicode=0 buffer=''
com.hs.touchUp

или после теста passthrough:

com.hs.touchDown
SCAN_QR_DIAG: dedicated_scan_key_passthrough keyCode=289 action=0
com.hs.touchUp
SCAN_QR_DIAG: dedicated_scan_key_passthrough keyCode=289 action=1

После return false:

com.hs.touchDown
SCAN_QR_DIAG: dedicated_scan_key_return_false keyCode=289 action=0
com.hs.touchUp
SCAN_QR_DIAG: dedicated_scan_key_return_false keyCode=289 action=1

Вывод: проблема не в том, что Kotlin полностью “съел” кнопку. Системный слой xc видит touchDown/touchUp, но vendor stack не продолжает цепочку до OemScanDemo.
Найденные vendor-компоненты через dumpsys
com.hs.dcsservice

Найдено:

Activity:
com.hs.dcsservice/com.hs.scanbutton.SplashActivity

Activity action:
com.hs.dcsservice.action -> com.hs.dcsservice/.SettingActivity

Permission:
com.hs.scanbutton.permission.DECODE

SplashActivity — это vendor scanner tools UI. Его запуск может разбудить сканер, но это плохой workaround:

    всплывает чужой экран;

    ломается UX;

    нужен возврат в наше приложение;

    иногда нужно ждать 3–5 секунд;

    после camera scan всё равно нестабильно.

com.hs.scanservice

Найдено:

Service:
com.hs.scanservice/.DecodeService

Service action:
com.hs.scanservice.action

Required permission:
com.honeywell.decode.permission.DECODE

Это реальный decode service.
Проверенный warmup через DecodeService

Пробовали запускать/bind-ить:

Intent("com.hs.scanservice.action")

и/или явно:

ComponentName("com.hs.scanservice", "com.hs.scanservice.DecodeService")

В логах это срабатывает:

HS_BOOTSTRAP: warmup explicit startService ok
HS_BOOTSTRAP: warmup action startService ok
HS_BOOTSTRAP: warmup bindService result=true
HS_BOOTSTRAP: warmup bindService connected name=ComponentInfo{com.hs.scanservice/com.hs.scanservice.DecodeService}
HS_BOOTSTRAP: warmup unbindService ok

Но после этого hard scan key внутри приложения всё равно не запускает луч.

Вывод: DecodeService можно поднять, но это не равно “начать сканирование”. Сервис живой, но trigger routing не доходит до active decode.
Что НЕ сработало
1. Запуск vendor SplashActivity

Плюсы:

    иногда оживляет scanner stack.

Минусы:

    открывает vendor UI;

    нужен возврат в приложение;

    может стартовать несколько раз;

    после camera scan нестабильно;

    плохой UX.

2. SCANRESTART

Ранее проверялось. Стабильного результата не дало.
3. Broadcast touchDown/touchUp из приложения

Программная отправка:

sendBroadcast(Intent("com.hs.touchDown"))
sendBroadcast(Intent("com.hs.touchUp"))

не дала эквивалента реальному нажатию кнопки.

На рабочем столе реальный hard key запускает цепочку scanservice -> dcsservice -> OemScanDemo, а программный broadcast из нашего app этого стабильно не делает.
4. dispatchKeyEvent passthrough

Пробовали для keyCode 289/290:

return super.dispatchKeyEvent(event)

Результат: не помогло.
5. dispatchKeyEvent return false

Пробовали:

return false

Результат: не помогло.

Событие логируется, но vendor decode всё равно не стартует.
6. FLAG_ALT_FOCUSABLE_IM

Пробовали:

window.setFlags(
    WindowManager.LayoutParams.FLAG_ALT_FOCUSABLE_IM,
    WindowManager.LayoutParams.FLAG_ALT_FOCUSABLE_IM
)

Результат: не помогло.
7. FLAG_NOT_FOCUSABLE

Пробовали:

window.addFlags(WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE)

Результат:

    меняет focus/input-поведение;

    приложение/WebView начинает вести себя плохо;

    были ANR / app isn't responding;

    как решение не подходит.

Вывод по гипотезе keyboard/input

Гипотеза была такая: наше приложение перехватывает hard scan key как клавиатурное событие, поэтому vendor scanner stack не получает кнопку.

Проверка показала:

    dispatchKeyEvent действительно видит keyCode 289/290;

    но даже при return super и return false vendor stack не запускается;

    системный xc всё равно логирует com.hs.touchDown/touchUp.

Значит проблема глубже, чем Kotlin key handling.

Скорее всего, vendor scanner stack принимает решение запускать decode в зависимости от foreground window/focus/app mode. Когда foreground — launcher, decode запускается. Когда foreground — наше fullscreen приложение, decode не запускается.
Вывод по dumpsys window

В состоянии нашего приложения foreground видно:

Window com.example.ocrscannertest/com.example.ocrscannertest.MainActivity
type=BASE_APPLICATION

Также видно много overlay-окон:

package=com.hs.dcsservice
type=PHONE
type=APPLICATION_OVERLAY

На рабочем столе scanner работает, но когда наше приложение foreground — не работает.

Вывод: наличие overlay-окон com.hs.dcsservice само по себе не гарантирует работу hard scan. Отличие именно в foreground/focused BASE_APPLICATION.
Текущая рабочая гипотеза

Проблема в vendor trigger routing / foreground policy.

Hard key проходит до системного слоя:

com.hs.touchDown
com.hs.touchUp

Но vendor stack не переводит это событие в decode, когда активное foreground-приложение — com.example.ocrscannertest.

Нужно найти vendor-настройку или API, который разрешает hardware scan поверх foreground-приложений.
Что искать дальше

Ключевые направления поиска:

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

Ключевые package/action/permission:

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

Практический следующий шаг

Нужно проверить настройки vendor scanner apps:

adb shell find /data/user/0/com.hs.dcsservice /data/user/0/com.hs.scanservice -maxdepth 4 -type f 2>/dev/null

Особенно интересны файлы:

shared_prefs/*.xml
databases/*
files/*

Дальше искать ключи:

broadcast
wedge
scan
trigger
focus
focal
keyboard
foreground
decode
barcode
output

Команды для чтения:

adb shell ls -la /data/user/0/com.hs.dcsservice/shared_prefs 2>/dev/null
adb shell ls -la /data/user/0/com.hs.scanservice/shared_prefs 2>/dev/null

adb shell cat /data/user/0/com.hs.dcsservice/shared_prefs/*.xml 2>/dev/null
adb shell cat /data/user/0/com.hs.scanservice/shared_prefs/*.xml 2>/dev/null

Важный вывод для разработки

Не делать ставку на бесконечные warmup/watchdog/restart циклы.

Причина: warmup может поднять service, но не меняет trigger routing policy.

Правильное решение должно быть одно из:

    Найти vendor API для direct trigger/decode.

    Найти настройку vendor stack: broadcast mode / foreground app scan allowed.

    Использовать официальную Honeywell AIDC API, если она реально поддерживается на этом устройстве.

    Если vendor stack закрытый и не даёт API — оставить camera scan как основной вариант, а laser scan использовать только после ручной настройки scanner mode в vendor tools.

Текущий статус

    Hardware key физически работает.

    Scanner engine физически работает.

    BARCODE_SEND приходит, когда vendor stack реально декодирует.

    Внутри нашего foreground app trigger не доходит до active decode.

    DecodeService найден и bind работает.

    Проблема не решена warmup-ом.

    Следующий шаг — исследовать vendor preferences / hidden settings / AIDC API.
    
    
    
    Да, выводы по dumpsys package важные.
Что нашли
com.hs.dcsservice

Есть:

Activity:
com.hs.dcsservice/com.hs.scanbutton.SplashActivity

Есть UI-настройка:

com.hs.dcsservice.action -> com.hs.dcsservice/.SettingActivity

Есть permission:

com.hs.scanbutton.permission.DECODE

com.hs.scanservice

Ключевая находка:

Service Resolver Table:
  com.hs.scanservice.action:
    com.hs.scanservice/.DecodeService
    permission com.honeywell.decode.permission.DECODE

То есть реальный vendor decode service запускается так:

Intent("com.hs.scanservice.action")

и сервис защищён permission:

com.honeywell.decode.permission.DECODE

Но по твоим логам уже видно: startService/bindService срабатывает, service подключается, но само сканирование не стартует, если наше приложение foreground.
Главный вывод

Сейчас проблема уже не “как найти сервис”. Сервис найден.

Проблема ниже уровнем:

hard button -> system xc -> touchDown/touchUp

доходит всегда, но дальше поведение разное.
На рабочем столе

touchDown
com.hs.scanservice
com.hs.dcsservice.action
OemScanDemo onKeepGoingCallback

В нашем приложении

touchDown
touchUp

и всё. Нет:

com.hs.scanservice
OemScanDemo onKeepGoingCallback
BARCODE_SEND

Значит foreground MainActivity блокирует не Android key event, а vendor trigger routing / focus policy.
Что я бы добавил в SCANNER_STATE_NOTES.md

Вставь в конец файла:

---

## 2026-04-24: выводы по `dumpsys package`

### Найденные vendor-компоненты

#### `com.hs.dcsservice`

```text
Activity:
com.hs.dcsservice/com.hs.scanbutton.SplashActivity

Activity action:
com.hs.dcsservice.action -> com.hs.dcsservice/.SettingActivity

Permission:
com.hs.scanbutton.permission.DECODE

SplashActivity — UI vendor scanner tools. Его запуск действительно может “разбудить” сканер, но это плохой UX: всплывает чужой экран, нужен возврат в наше приложение, иногда требуется ждать 3–5 секунд.

SettingActivity открывается через action:

com.hs.dcsservice.action

Вероятно, это экран/обработчик настроек vendor scanner stack.
com.hs.scanservice

Service:
com.hs.scanservice/.DecodeService

Service action:
com.hs.scanservice.action

Required permission:
com.honeywell.decode.permission.DECODE

Это реальный decode service. Он находится не в com.hs.dcsservice, а в com.hs.scanservice.
Проверенный факт

startService / bindService к com.hs.scanservice/.DecodeService срабатывает:

warmup explicit startService ok
warmup action startService ok
warmup bindService result=true
warmup bindService connected name=ComponentInfo{com.hs.scanservice/com.hs.scanservice.DecodeService}

Но это не гарантирует запуск луча/декодирования.
Почему простой warmup не решает проблему

После bindService сервис живой, но при нажатии hard scan key внутри нашего приложения нет цепочки:

com.hs.scanservice
com.hs.dcsservice.action
OemScanDemo onKeepGoingCallback
BARCODE_SEND

В логах есть только:

D/xc: com.hs.touchDown
D/xc: com.hs.touchUp

Это означает: системный обработчик кнопки видит нажатие, но vendor stack не переводит это событие в активное сканирование, пока наше приложение находится foreground/fullscreen.
Сравнение состояний
Рабочий стол / launcher — laser scan работает

touchDown
com.hs.scanservice
com.hs.dcsservice.action
OemScanDemo onKeepGoingCallback
decode success
BARCODE_SEND

Наше приложение foreground — laser scan не работает

touchDown
touchUp

Нет:

com.hs.scanservice
OemScanDemo onKeepGoingCallback
BARCODE_SEND

Текущая рабочая гипотеза

Проблема не в Kotlin dispatchKeyEvent, не в onKeyDown, не в keyboard wedge.

Проблема, вероятно, в vendor trigger routing/focus policy:

    когда MainActivity является активным fullscreen BASE_APPLICATION, vendor stack не запускает decode по hard trigger;

    когда foreground — launcher/home, vendor stack запускает decode нормально;

    com.hs.dcsservice overlay-окна существуют в обоих случаях, но этого недостаточно;

    DecodeService можно запустить/bind-нуть, но без правильного trigger state он не начинает сканирование.

Что НЕ сработало
Метод	Результат
return super.dispatchKeyEvent() для keyCode 289/290	не помогло
return false для keyCode 289/290	не помогло
FLAG_ALT_FOCUSABLE_IM	не помогло
FLAG_NOT_FOCUSABLE	частично меняет input focus, но ломает приложение / WebView / вызывает ANR
startService/bindService DecodeService	сервис подключается, но луч не стартует
SCANRESTART	ранее не дал стабильного эффекта
программный com.hs.touchDown/touchUp	не эквивалентен рабочему hard trigger внутри нашего foreground app
Что нужно искать дальше

Нужно найти vendor API/настройку, которая включает режим сканирования поверх foreground app:

    enable scan trigger in foreground apps;

    keyboard wedge vs broadcast intent mode;

    scan output mode;

    focus mode / focal mode;

    allow scan in app;

    disable floating scan overlay focus dependency;

    set scanner trigger mode;

    enable decode on hardware key globally.

Ключевые package/action/permission для дальнейшего поиска:

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

Практическое направление

Не делать бесконечный warmup и не пытаться “вернуть приложение” после vendor UI.

Правильное направление — найти способ конфигурации vendor scanner stack, чтобы результат всегда приходил через broadcast:

com.android.hs.action.BARCODE_SEND

независимо от того, что foreground app — com.example.ocrscannertest.

Пока это не найдено, временный workaround — использовать camera scan/OCR внутри приложения, а laser scan стабильно работает только после vendor/launcher focus state.


## Что дальше проверить

Запусти ещё одну диагностику, она может показать настройки vendor app:

```bash
adb shell find /data/user/0/com.hs.dcsservice /data/user/0/com.hs.scanservice -maxdepth 3 -type f 2>/dev/null

Если увидим shared_prefs/*.xml, можно будет посмотреть, есть ли там ключи типа broadcast, wedge, scan_mode, key, trigger, foreground, focus.

adbd cannot run as root in production builds

Вывод

Устройство production build, adbd не может перейти в root. Значит:

adb shell find /data/user/0/com.hs.dcsservice ...

не даст полезного результата без root/su. Этот путь пока закрыт.
Что фиксируем в диагностике

Добавь в SCANNER_STATE_NOTES.md:

## ADB root недоступен

Проверка:

```bash
adb root

Результат:

adbd cannot run as root in production builds

Вывод:

    устройство собрано как production build;

    adbd не может работать от root;

    private data каталогов системных приложений недоступны через обычный adb shell.

Недоступные каталоги:

/data/user/0/com.hs.dcsservice
/data/user/0/com.hs.scanservice

Это не доказывает, что там нет конфигов или логов. Это только означает, что shell не имеет прав их читать.

Дальше диагностику ведем через:

dumpsys package
dumpsys window windows
logcat
am startservice
am broadcast
settings get/list
cmd package


## Практический следующий шаг

Раз root нет, надо идти не через private-файлы, а через **публичные entrypoints**.

У нас уже найден важный endpoint:

```text
com.hs.scanservice/.DecodeService
Action: com.hs.scanservice.action
Permission: com.honeywell.decode.permission.DECODE

Следующая проверка:

adb shell am startservice \
  -a com.hs.scanservice.action \
  -n com.hs.scanservice/.DecodeService

И параллельно лог:

adb logcat -v time | grep -i -E "scanservice|DecodeService|OemScanDemo|dcsservice|BARCODE_SEND|touchDown|touchUp|SCAN_QR_DIAG|HS_BOOTSTRAP"

Если сервис стартует, но OemScanDemo не оживает внутри app — значит дело точно не в старте сервиса, а в оконном фокусе / overlay / input routing.
