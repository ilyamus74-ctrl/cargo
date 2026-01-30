# Native flow refactor checklist — пункт 5 (разделение Web / APP)

Дата: 2026-02-04

## 5) Разделение Web / APP поведения (гибрид)

### 5.1. Механизм передачи контекста в APP (только при наличии DeviceApp)
- В веб-коде `emitDeviceContext()` читает `device-scan-config` и связанные блоки (`ocr-templates`, `ocr-templates-destcountry`, `ocr-dicts`), но отправляет payload только если доступен `window.DeviceApp.onMainContext`. Это означает, что в обычном браузере вызов просто не выполняется. 【F:www/js/core_api.js†L1279-L1313】
- В приложении WebView регистрирует JavaScript-интерфейс `DeviceApp` через `addJavascriptInterface(DeviceBridge(), "DeviceApp")`, а `DeviceBridge.onMainContext` принимает JSON-пэйлоад и прокидывает обновление контекста в APP. 【F:APP/OCRScanner/src/main/java/com/example/ocrscannertest/MainActivity.kt†L2640-L2684】

### 5.2. Разделение ответственности
- **APP**: при наличии `DeviceApp` получает `device-scan-config` и использует его для нативного исполнения flow (через обработчик `onContextUpdated`). 【F:APP/OCRScanner/src/main/java/com/example/ocrscannertest/MainActivity.kt†L2648-L2676】
- **Web**: остаётся на JS-логике и не зависит от наличия `DeviceApp`; отправка контекста в APP — опциональный путь, который не мешает браузерному сценарию. 【F:www/js/core_api.js†L1301-L1312】

## Отметка по чеклисту
- [x] Пункт 5 (разделение Web / APP поведения) — подтверждён: передача контекста выполняется только при наличии `DeviceApp`, а WebView в APP подхватывает JSON через `DeviceBridge`.
