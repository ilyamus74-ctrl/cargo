@file:OptIn(androidx.camera.core.ExperimentalGetImage::class)

package com.example.ocrscannertest
import android.content.Context
import android.os.Build
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.InputStream
import java.net.HttpURLConnection
import java.net.URL
import android.Manifest
import android.content.pm.PackageManager
import android.os.Bundle
import android.view.WindowManager
import androidx.activity.ComponentActivity
import androidx.activity.OnBackPressedCallback
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Menu
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.lifecycle.LifecycleOwner
import com.example.ocrscannertest.ui.theme.OcrScannerTestTheme
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.barcode.BarcodeScannerOptions
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.common.InputImage
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import androidx.camera.core.*
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import java.util.concurrent.Executors
import javax.net.ssl.HttpsURLConnection
import javax.net.ssl.SSLContext
import javax.net.ssl.TrustManager
import javax.net.ssl.X509TrustManager
import java.security.SecureRandom
import java.security.cert.X509Certificate
import javax.net.ssl.HostnameVerifier
import javax.net.ssl.SSLSession
import android.view.ViewGroup
import android.webkit.WebView
import android.webkit.WebViewClient
import android.webkit.SslErrorHandler
import android.net.http.SslError
import android.webkit.CookieManager
import android.view.KeyEvent
import com.google.mlkit.vision.text.TextRecognition
import com.google.mlkit.vision.text.latin.TextRecognizerOptions
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import android.webkit.JavascriptInterface
import android.os.Handler
import android.os.Looper
import android.net.Uri
import androidx.compose.ui.text.font.FontWeight


private val INSTALL_MAIN_OBSERVER_JS = """
(function(){
  if (window.__deviceMainObserverInstalled) return;
  window.__deviceMainObserverInstalled = true;

function read(id){
  var el = document.getElementById(id);
  if(!el) return null;
  var t = (el.textContent || el.innerText || "").trim();
  if (!t || t === "null" || t === "undefined") return null;
  return t;
}

  function emit(){
    try{
      var payload = {
        task: read("device-scan-config"),
        ocr_templates: read("ocr-templates"),
        destcountry: read("ocr-templates-destcountry"),
        ////dicts: read("ocr-dicts")
      };
      if (window.DeviceApp && window.DeviceApp.onMainContext) {
        window.DeviceApp.onMainContext(JSON.stringify(payload));
      }
    } catch(e){}
  }

  var t=null;
  function schedule(){ clearTimeout(t); t=setTimeout(emit,120); }

var root = document.getElementById("main") || document.body;
if (root) new MutationObserver(schedule).observe(root, {childList:true, subtree:true});


  emit();
})();
""".trimIndent()

enum class WarehouseScanStep { BARCODE, OCR, MEASURE, SUBMIT }
class MainActivity : ComponentActivity() {
    companion object {
        var onVolumeDown: (() -> Unit)? = null
        var onVolumeUp:   (() -> Unit)? = null
    }

    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        when (keyCode) {
            KeyEvent.KEYCODE_VOLUME_DOWN -> {
                onVolumeDown?.invoke()
                return true
            }
            KeyEvent.KEYCODE_VOLUME_UP -> {
                onVolumeUp?.invoke()
                return true
            }
        }
        return super.onKeyDown(keyCode, event)
    }
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // экран не гаснет — поведение киоска
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        enableEdgeToEdge()

        // блокируем "Назад"
        onBackPressedDispatcher.addCallback(
            this,
            object : OnBackPressedCallback(true) {
                override fun handleOnBackPressed() {
                    // игнорируем
                }
            }
        )

        setContent {
            OcrScannerTestTheme {
                AppRoot()
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AppRoot() {
    val context = LocalContext.current
    val repo = remember { DeviceConfigRepository(context) }
    val scope = rememberCoroutineScope()

    var config by remember { mutableStateOf(repo.load()) }

    var showSettings by remember { mutableStateOf(!config.enrolled || config.serverUrl.isBlank()) }
    var showQrScan by remember { mutableStateOf(false) }
    var showWebView by remember { mutableStateOf(false) }

    // новый экран для OCR
    var showOcr by remember { mutableStateOf(false) }

    // ссылка на WebView
    var webViewRef by remember { mutableStateOf<WebView?>(null) }
    var ocrTemplates by remember { mutableStateOf<OcrTemplates?>(null) }
    var lastQr by remember { mutableStateOf<String?>(null) }
    var loginError by remember { mutableStateOf<String?>(null) }
    var isLoggingIn by remember { mutableStateOf(false) }
    var destConfig by remember { mutableStateOf<List<DestCountryCfg>>(emptyList()) }

    var nameDict by remember { mutableStateOf<NameDict?>(null) }

    // session_id из сервера (по факту сейчас только храним для информации)
    var sessionId by remember { mutableStateOf<String?>(null) }

    var menuExpanded by remember { mutableStateOf(false) }
    var taskConfigJson by remember { mutableStateOf<String?>(null) }
    var taskConfig by remember { mutableStateOf<ScanTaskConfig?>(null) }

    var ocrIsDefault by remember { mutableStateOf(false) }

    // Шаги потока складского сканирования

    var warehouseScanStep by remember { mutableStateOf(WarehouseScanStep.BARCODE) }

    // Оверлей штрих-кодов
    var showBarcodeScan by remember { mutableStateOf(false) }

    // трекинг двойного нажатия
    var lastVolumeUpTs by remember { mutableStateOf(0L) }

    // колбэк, который будет вызываться при VOL_DOWN, когда открыт OCR
    var ocrHardwareTrigger by remember { mutableStateOf<(() -> Unit)?>(null) }
    var barcodeHardwareTrigger by remember { mutableStateOf<(() -> Unit)?>(null) }
    LaunchedEffect(showWebView, showOcr, showBarcodeScan, ocrHardwareTrigger, barcodeHardwareTrigger, webViewRef) {
        when {
            showBarcodeScan && barcodeHardwareTrigger != null -> {
                MainActivity.onVolumeDown = {
                    barcodeHardwareTrigger?.invoke()
                }
                MainActivity.onVolumeUp = null
            }

            // Если открыт экран OCR и есть триггер — VOL_DOWN стреляет OCR
            showOcr && ocrHardwareTrigger != null -> {
                MainActivity.onVolumeDown = {
                    ocrHardwareTrigger?.invoke()
                }
                MainActivity.onVolumeUp = null
            }

            // Если открыт WebView (форма склада)
            showWebView -> {
                val isWarehouseIn = taskConfig?.taskId == "warehouse_in"

                fun handleDoubleVolumeUp(): Boolean {
                    val now = System.currentTimeMillis()
                    val delta = now - lastVolumeUpTs
                    lastVolumeUpTs = now
                    val isDouble = delta in 50..450
                    if (isDouble) {
                        webViewRef?.let { web -> clearParcelFormInWebView(web) }
                        warehouseScanStep = WarehouseScanStep.BARCODE
                        showOcr = false
                        showBarcodeScan = false
                    }
                    return isDouble
                }
                MainActivity.onVolumeDown = {
                    if (isWarehouseIn) {
                        when (warehouseScanStep) {
                            WarehouseScanStep.BARCODE -> {
                                webViewRef?.let { web -> clearTrackingAndTuidInWebView(web) }
                                showBarcodeScan = true
                            }
                            WarehouseScanStep.OCR -> {
                                ocrIsDefault = taskConfig == null
                                showOcr = true
                            }
                            WarehouseScanStep.MEASURE -> {
                                webViewRef?.let { web ->
                                    withStandDeviceSelected(web) { selected ->
                                        if (selected) {
                                            requestStandMeasurementInWebView(web)
                                            warehouseScanStep = WarehouseScanStep.SUBMIT
                                        } else {
                                            prepareFormForNextScanInWebView(web)
                                            warehouseScanStep = WarehouseScanStep.BARCODE
                                        }
                                    }
                                }
                            }
                            WarehouseScanStep.SUBMIT -> {
                                webViewRef?.let { web -> prepareFormForNextScanInWebView(web) }
                                warehouseScanStep = WarehouseScanStep.BARCODE
                            }
                        }
                    } else {
                        webViewRef?.let { web -> prepareFormForNextScanInWebView(web) }

                        val hasTask = taskConfig != null
                        ocrIsDefault = !hasTask
                        showOcr = true

                        when (taskConfig?.defaultMode) {
                            "qr"      -> { showQrScan = true }
                            "barcode" -> { /* showBarcodeScan = true */ }
                            "ocr"     -> { showOcr = true }
                            else      -> { showOcr = true } // fallback
                        }
                    }
                }

                MainActivity.onVolumeUp = volumeUp@{
                    if (isWarehouseIn) {
                        if (handleDoubleVolumeUp()) return@volumeUp

                        when (warehouseScanStep) {
                            WarehouseScanStep.BARCODE -> {
                                webViewRef?.let { web -> clearTrackingAndTuidInWebView(web) }
                            }
                            WarehouseScanStep.OCR -> {
                                webViewRef?.let { web -> clearParcelFormExceptTrack(web) }
                                warehouseScanStep = WarehouseScanStep.OCR
                            }
                            WarehouseScanStep.MEASURE -> {
                                webViewRef?.let { web ->
                                    withStandDeviceSelected(web) { selected ->
                                        if (selected) {
                                            clearMeasurementsInWebView(web)
                                        } else {
                                            clearParcelFormExceptTrack(web)
                                            warehouseScanStep = WarehouseScanStep.OCR
                                        }
                                    }
                                }
                            }
                            WarehouseScanStep.SUBMIT -> {
                                webViewRef?.let { web ->
                                    withStandDeviceSelected(web) { selected ->
                                        if (selected) {
                                            clearMeasurementsInWebView(web)
                                            warehouseScanStep = WarehouseScanStep.MEASURE
                                        } else {
                                            clearParcelFormExceptTrack(web)
                                            warehouseScanStep = WarehouseScanStep.OCR
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        webViewRef?.let { web -> clearParcelFormInWebView(web) }
                    }
                }

            }

            // В остальных экранах кнопки громкости не трогаем
            else -> {
                MainActivity.onVolumeDown = null
                MainActivity.onVolumeUp = null
            }
        }
    }
    Scaffold(
        topBar = {
            // как и было: прячем верхнюю панель, когда открыт WebView
            if (!showWebView) {
                TopAppBar(
                    title = {
                        Text(
                            when {
                                showSettings -> "Настройки устройства"
                                showQrScan   -> "Сканирование QR"
                                else         -> "Сканер / терминал"
                            }
                        )
                    },
                    navigationIcon = {
                        Box {
                            IconButton(onClick = { menuExpanded = true }) {
                                Icon(
                                    imageVector = Icons.Default.Menu,
                                    contentDescription = "Меню"
                                )
                            }

                            DropdownMenu(
                                expanded = menuExpanded,
                                onDismissRequest = { menuExpanded = false }
                            ) {
                                DropdownMenuItem(
                                    text = { Text("Настройки") },
                                    onClick = {
                                        menuExpanded = false
                                        showSettings = true
                                        showQrScan = false
                                        showWebView = false
                                    }
                                )
                            }
                        }
                    }
                )
            }
        }
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .padding(innerPadding)
                .fillMaxSize()
        ) {

            // ОСНОВНОЙ КОНТЕНТ (как раньше, но БЕЗ showOcr)
            when {
                showSettings -> {
                    SettingsScreen(
                        modifier = Modifier.fillMaxSize(),
                        config = config,
                        onConfigChanged = { newCfg ->
                            config = newCfg
                            repo.save(newCfg)
                        },
                        onEnrollSuccess = { token ->
                            val updated = config.copy(
                                deviceToken = token,
                                enrolled = true
                            )
                            config = updated
                            repo.save(updated)
                            showSettings = false
                        },
                        onUnenroll = {
                            repo.clearEnroll()
                            config = repo.load()

                            val cm = CookieManager.getInstance()
                            cm.removeAllCookies(null)
                            cm.flush()

                            sessionId = null
                            showWebView = false
                            lastQr = null
                            loginError = null

                            showSettings = true
                            showQrScan = false
                            showWebView = false
                        },
                        onClose = {
                            showSettings = false
                        }
                    )
                }

                showQrScan -> {
                    QrScanScreen(
                        modifier = Modifier.fillMaxSize(),
                        onCodeScanned = { raw ->
                            showQrScan = false
                            lastQr = raw
                            loginError = null
                            isLoggingIn = true

                            scope.launch {
                                val result = qrLoginOnServer(context, config, raw)
                                isLoggingIn = false

                                if (result.ok && !result.sessionId.isNullOrBlank()) {
                                    sessionId = result.sessionId

                                    val base = normalizeServerUrl(config.serverUrl)
                                    val cookieManager = CookieManager.getInstance()
                                    cookieManager.setAcceptCookie(true)
                                   // val cookieStr = "PHPSESSID=${result.sessionId}; Path=/; Secure"
                                    val isHttps = base.startsWith("https://", ignoreCase = true)

                                    val cookieStr = buildString {
                                        append("PHPSESSID=${result.sessionId}; Path=/; SameSite=Lax")
                                        if (isHttps) append("; Secure")
                                    }
                                    cookieManager.setCookie(base, cookieStr)
                                    cookieManager.flush()

                                    showWebView = true
                                } else {
                                    loginError = result.errorMessage ?: "Ошибка логина по QR"
                                }
                            }
                        },
                        onCancel = {
                            showQrScan = false
                        }
                    )
                }

                showWebView -> {
                    DeviceWebViewScreen(
                        modifier = Modifier.padding(innerPadding),
                        config = config,
                        onWebViewReady = { webView -> webViewRef = webView },

                        onContextUpdated = { taskJson: String?, tmplJson: String?, destJson: String?, dictJson: String? ->
                            taskConfigJson = taskJson
                            taskConfig = taskJson?.let { parseScanTaskConfig(it) }

                            tmplJson?.let { ocrTemplates = parseOcrTemplates(it) }
                            destJson?.let { destConfig = parseDestConfigJson(it) }

                            dictJson?.let {
                                val parsed = parseNameDictJson(it)
                                if (config.syncNameDict && parsed != null) {
                                    nameDict = parsed
                                }
                            }
                        },
                        onSessionEnded = {
                            val cm = CookieManager.getInstance()
                            cm.removeAllCookies(null)
                            cm.flush()

                            sessionId = null
                            showWebView = false
                            lastQr = null
                            loginError = "Сессия завершена"
                        },
                        onTemplatesLoaded = { tmpl ->
                            ocrTemplates = tmpl
                            // если хочешь проверить — можно временно повесить лог
                            // println("OCR templates loaded: $tmpl")
                        },
                        onNameDictLoaded = { dict ->
                            if (config.syncNameDict && dict != null) {
                                nameDict = dict
                            }
                        }
                    )
                }

                else -> {
                    LoginReadyScreen(
                        modifier = Modifier.fillMaxSize(),
                        config = config,
                        lastQr = lastQr,
                        isLoggingIn = isLoggingIn,
                        loginError = loginError,
                        onScanQr = {
                            showWebView = false
                            showQrScan = true
                        }
                    )
                }
            }


            // Оверлей BARCODE поверх WebView
            if (showBarcodeScan) {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background.copy(alpha = 0.98f)
                ) {
                    val isWarehouseIn = taskConfig?.taskId == "warehouse_in"
                    BarcodeScanScreen(
                        modifier = Modifier.fillMaxSize(),
                        onResult = { raw ->
                            showBarcodeScan = false
                            val cleanBarcode = sanitizeBarcodeInput(raw)
                            val data = buildParcelFromBarcode(cleanBarcode, sanitizeInput = false)
                            webViewRef?.let { web ->
                                fillBarcodeUsingTemplate(web, cleanBarcode, taskConfig?.barcodeAction)
                                fillParcelFormInWebView(web, data)
                            }
                            if (isWarehouseIn) {
                                warehouseScanStep = WarehouseScanStep.OCR
                            }
                        },
                        onCancel = {
                            showBarcodeScan = false
                        },
                        onBindHardwareTrigger = { action ->
                            barcodeHardwareTrigger = action
                        }
                    )
                }
            }

            // ОВЕРЛЕЙ OCR ПОВЕРХ WebView (и любого экрана)
            if (showOcr) {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background.copy(alpha = 0.98f)
                ) {
                    val isWarehouseIn = taskConfig?.taskId == "warehouse_in"
                    OcrScanScreen(
                        modifier = Modifier.fillMaxSize(),
                        destConfig = destConfig,
                        config = config,
                        nameDict = nameDict,
                        isDefaultMode = ocrIsDefault,
                        onResult = { ocrData ->
                            showOcr = false
                            webViewRef?.let { web ->
                                fillParcelFormInWebView(web, ocrData)
                            }
                            if (isWarehouseIn) {
                                warehouseScanStep = WarehouseScanStep.MEASURE
                            }
                        },
                        onCancel = {
                            showOcr = false
                        },
                        onBindHardwareTrigger = { action ->
                            ocrHardwareTrigger = action
                        },
                        onBarcodeClick = {
                            showOcr = false
                            showBarcodeScan = true
                        },
                        onBpClick = {
                            webViewRef?.let { web ->
                                requestStandMeasurementInWebView(web)
                            }
                        }
                    )
                }
            }

        }
    }
}

fun normalizeServerUrl(raw: String): String {
    var s = raw.trim()
    s = s.removeSuffix("/")
    s = s.removeSuffix("/api/device_enroll.php")
    return s
}
@Composable
fun SettingsScreen(
    modifier: Modifier = Modifier,
    config: DeviceConfig,
    onConfigChanged: (DeviceConfig) -> Unit,
    onEnrollSuccess: (String) -> Unit,
    onUnenroll: () -> Unit,
    onClose: () -> Unit
) {
    val scope = rememberCoroutineScope()
    val context = LocalContext.current

    var serverUrl by remember { mutableStateOf(config.serverUrl) }
    var deviceName by remember { mutableStateOf(config.deviceName) }
    var statusText by remember { mutableStateOf("") }
    var isBusy by remember { mutableStateOf(false) }
    var allowInsecure by remember { mutableStateOf(config.allowInsecureSsl) }
    var useRemoteOcr by remember { mutableStateOf(config.useRemoteOcr) }
    var syncNameDict by remember { mutableStateOf(config.syncNameDict) }

    val scrollState = rememberScrollState()

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(16.dp)
            .verticalScroll(scrollState)
    ) {
        Text("Настройки устройства", style = MaterialTheme.typography.titleLarge)

        Spacer(Modifier.height(16.dp))

        OutlinedTextField(
            value = serverUrl,
            onValueChange = { newVal -> serverUrl = newVal },
            label = { Text("Server URL") },
            singleLine = true,
            modifier = Modifier.fillMaxWidth()
        )

        Spacer(Modifier.height(8.dp))

        OutlinedTextField(
            value = deviceName,
            onValueChange = { newVal -> deviceName = newVal },
            label = { Text("Имя устройства") },
            singleLine = true,
            modifier = Modifier.fillMaxWidth()
        )

        Spacer(Modifier.height(12.dp))

        // --- SSL ---
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.fillMaxWidth()
        ) {
            Checkbox(
                checked = allowInsecure,
                onCheckedChange = { allowInsecure = it }
            )
            Spacer(Modifier.width(8.dp))
            Column {
                Text(
                    "Игнорировать ошибки сертификата",
                    style = MaterialTheme.typography.bodyMedium
                )
                Text(
                    "(НЕБЕЗОПАСНО, только для теста)",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.error
                )
            }
        }

        Spacer(Modifier.height(8.dp))

        // --- удалённый OCR ---
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.fillMaxWidth()
        ) {
            Checkbox(
                checked = useRemoteOcr,
                onCheckedChange = { useRemoteOcr = it }
            )
            Spacer(Modifier.width(8.dp))
            Text(
                "Удалённый OCR-парсер",
                style = MaterialTheme.typography.bodyMedium
            )
        }

        Spacer(Modifier.height(8.dp))

        // --- словарь имён ---
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.fillMaxWidth()
        ) {
            Checkbox(
                checked = syncNameDict,
                onCheckedChange = { syncNameDict = it }
            )
            Spacer(Modifier.width(8.dp))
            Text(
                "Словарь имён с сервера",
                style = MaterialTheme.typography.bodyMedium
            )
        }

        Spacer(Modifier.height(16.dp))

        Text(text = statusText)

        Spacer(Modifier.height(16.dp))

        // КНОПКА 1: ПРИВЯЗАТЬ
        Button(
            onClick = {
                if (serverUrl.isBlank()) {
                    statusText = "Укажи Server URL"
                    return@Button
                }

                isBusy = true
                statusText = "Отправляю запрос на привязку…"

                scope.launch {
                    try {
                        val updated = config.copy(
                            serverUrl = serverUrl.trim(),
                            deviceName = deviceName.trim(),
                            allowInsecureSsl = allowInsecure,
                            useRemoteOcr = useRemoteOcr,
                            syncNameDict = syncNameDict
                        )

                        onConfigChanged(updated)

                        val result = enrollDeviceOnServer(context, updated)

                        if (result.ok && !result.deviceToken.isNullOrBlank()) {
                            statusText = "Устройство привязано"
                            onEnrollSuccess(result.deviceToken)
                        } else {
                            statusText =
                                "Ошибка привязки: ${result.errorMessage ?: "неизвестно"}"
                        }
                    } catch (e: Exception) {
                        e.printStackTrace()
                        statusText = "Сбой сети: ${e.message}"
                    } finally {
                        isBusy = false
                    }
                }
            },
            enabled = !isBusy,
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Привязать устройство")
        }

        Spacer(Modifier.height(8.dp))

        // КНОПКА 2: ОТВЯЗАТЬ
        Button(
            onClick = {
                isBusy = true
                statusText = "Отвязываю устройство…"

                scope.launch {
                    try {
                        // TODO: реальный HTTP /api/device_unenroll
                        delay(1000)
                        statusText = "Устройство отвязано"
                        onUnenroll()
                    } finally {
                        isBusy = false
                    }
                }
            },
            enabled = !isBusy && config.enrolled,
            colors = ButtonDefaults.buttonColors(
                containerColor = MaterialTheme.colorScheme.errorContainer,
                contentColor = MaterialTheme.colorScheme.onErrorContainer
            ),
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Отвязать устройство")
        }

        Spacer(Modifier.height(16.dp))

        OutlinedButton(
            onClick = { onClose() },
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Закрыть настройки")
        }
    }
}


@Composable
fun LoginReadyScreen(
    config: DeviceConfig,
    lastQr: String?,
    isLoggingIn: Boolean,
    loginError: String?,
    onScanQr: () -> Unit,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Text(
            text = "Устройство привязано к серверу:",
            style = MaterialTheme.typography.titleMedium
        )
        Spacer(modifier = Modifier.height(8.dp))
        Text(text = config.serverUrl)

        Spacer(modifier = Modifier.height(16.dp))

        Text(text = "Имя устройства: ${config.deviceName}")
        Text(text = "UID: ${config.deviceUid}")

        Spacer(modifier = Modifier.height(32.dp))

        Button(onClick = { onScanQr() }) {
            Text("Сканировать QR для входа")
        }

        Spacer(modifier = Modifier.height(24.dp))

        if (isLoggingIn) {
            Text(
                text = "Выполняю вход по QR…",
                style = MaterialTheme.typography.bodyMedium
            )
            Spacer(modifier = Modifier.height(8.dp))
        }

        if (!loginError.isNullOrBlank()) {
            Text(
                text = "Ошибка входа: $loginError",
                color = MaterialTheme.colorScheme.error,
                style = MaterialTheme.typography.bodyMedium
            )
            Spacer(modifier = Modifier.height(8.dp))
        }

        if (!lastQr.isNullOrBlank()) {
            Text(
                text = "Последний QR:\n$lastQr",
                style = MaterialTheme.typography.bodyMedium
            )
        }
    }
}

/**
 * Экран сканирования QR / штрихкодов.
 */
@Composable
fun QrScanScreen(
    modifier: Modifier = Modifier,
    onCodeScanned: (String) -> Unit,
    onCancel: () -> Unit
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current

    var hasCameraPermission by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(
                context,
                Manifest.permission.CAMERA
            ) == PackageManager.PERMISSION_GRANTED
        )
    }

    val permissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission()
    ) { granted ->
        hasCameraPermission = granted
    }

    LaunchedEffect(Unit) {
        if (!hasCameraPermission) {
            permissionLauncher.launch(Manifest.permission.CAMERA)
        }
    }

    Box(
        modifier = modifier.fillMaxSize()
    ) {


        if (!hasCameraPermission) {
            Column(
                modifier = Modifier
                    .align(Alignment.Center)
                    .padding(16.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text("Нет доступа к камере")
                Spacer(Modifier.height(16.dp))
                OutlinedButton(onClick = { onCancel() }) {
                    Text("Назад")
                }
            }
        } else {
            // превью + сканер
            QrCameraPreview(
                lifecycleOwner = lifecycleOwner,
                modifier = Modifier.fillMaxSize(),
                onCodeScanned = onCodeScanned
            )

            OutlinedButton(
                onClick = { onCancel() },
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .padding(16.dp)
            ) {
                Text("Отмена")
            }
        }
    }
}

/**
 * Обёртка над CameraX + ML Kit BarcodeScanning.
 * Берёт первый найденный код и отдаёт наверх.
 */

@Composable
fun QrCameraPreview(
    lifecycleOwner: LifecycleOwner,
    onCodeScanned: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current

    // PreviewView для CameraX
    val previewView = remember {
        PreviewView(context).apply {
            scaleType = PreviewView.ScaleType.FILL_CENTER
        }
    }

    LaunchedEffect(Unit) {
        val cameraProviderFuture = ProcessCameraProvider.getInstance(context)
        val cameraProvider = cameraProviderFuture.get()

        val preview = Preview.Builder()
            .build()
            .also { it.setSurfaceProvider(previewView.surfaceProvider) }

        val analysis = ImageAnalysis.Builder()
            .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
            .build()

        val options = BarcodeScannerOptions.Builder()
            .setBarcodeFormats(
                Barcode.FORMAT_QR_CODE,
                Barcode.FORMAT_AZTEC,
                Barcode.FORMAT_CODE_128,
                Barcode.FORMAT_CODE_39,
                Barcode.FORMAT_EAN_13,
                Barcode.FORMAT_EAN_8
            )
            .build()

        val scanner = BarcodeScanning.getClient(options)
        val executor = Executors.newSingleThreadExecutor()

        var found = false

        analysis.setAnalyzer(executor) { imageProxy ->
            if (found) {
                imageProxy.close()
                return@setAnalyzer
            }

            val mediaImage = imageProxy.image
            if (mediaImage == null) {
                imageProxy.close()
                return@setAnalyzer
            }

            val image = InputImage.fromMediaImage(
                mediaImage,
                imageProxy.imageInfo.rotationDegrees
            )

            scanner.process(image)
                .addOnSuccessListener { barcodes ->
                    val first = barcodes.firstOrNull()
                    val raw = first?.rawValue
                    if (!raw.isNullOrBlank()) {
                        found = true
                        onCodeScanned(raw)
                    }
                }
                .addOnFailureListener {
                    // можно залогировать ошибку, если надо
                }
                .addOnCompleteListener {
                    imageProxy.close()
                }
        }

        val selector = CameraSelector.DEFAULT_BACK_CAMERA

        try {
            cameraProvider.unbindAll()
            cameraProvider.bindToLifecycle(
                lifecycleOwner,
                selector,
                preview,
                analysis
            )
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    AndroidView(
        factory = { previewView },
        modifier = modifier
    )
}

data class RemoteOcrResult(
    val ok: Boolean,
    val data: OcrParcelData?,
    val errorMessage: String?
)

suspend fun callRemoteOcrParse(
    context: Context,
    cfg: DeviceConfig,
    rawText: String
): RemoteOcrResult = withContext(Dispatchers.IO) {
    val baseUrl = cfg.serverUrl.trim()
    if (baseUrl.isEmpty()) {
        return@withContext RemoteOcrResult(false, null, "Пустой Server URL")
    }

    val urlStr = baseUrl.trimEnd('/') + "/api/ocr_parse.php"
    val url = URL(urlStr)
    val conn = (url.openConnection() as HttpURLConnection)

    // тот же трест SSL, что и в enroll/qr_login
    if (conn is HttpsURLConnection && cfg.allowInsecureSsl) {
        val trustAllCerts = arrayOf<TrustManager>(
            object : X509TrustManager {
                override fun checkClientTrusted(
                    chain: Array<X509Certificate>,
                    authType: String
                ) { }

                override fun checkServerTrusted(
                    chain: Array<X509Certificate>,
                    authType: String
                ) { }

                override fun getAcceptedIssuers(): Array<X509Certificate> =
                    emptyArray()
            }
        )

        val sslContext = SSLContext.getInstance("TLS")
        sslContext.init(null, trustAllCerts, SecureRandom())
        conn.sslSocketFactory = sslContext.socketFactory

        conn.hostnameVerifier = HostnameVerifier { _: String?, _: SSLSession? ->
            true
        }
    }

    try {
        conn.requestMethod = "POST"
        conn.connectTimeout = 5000
        conn.readTimeout = 5000
        conn.doOutput = true
        conn.setRequestProperty(
            "Content-Type",
            "application/x-www-form-urlencoded; charset=utf-8"
        )

        // PHP ждёт $_POST['raw_text'], значит шлём form-encoded
        val body = "raw_text=" + java.net.URLEncoder.encode(rawText, "UTF-8")
        conn.outputStream.use { os ->
            os.write(body.toByteArray(Charsets.UTF_8))
        }

        val code = conn.responseCode
        val stream: InputStream? =
            if (code in 200..299) conn.inputStream else conn.errorStream

        val respText = stream
            ?.bufferedReader(Charsets.UTF_8)
            ?.use { it.readText() }
            ?: ""

        if (respText.isBlank()) {
            return@withContext RemoteOcrResult(false, null, "Пустой ответ сервера (HTTP $code)")
        }

        val obj = try {
            JSONObject(respText)
        } catch (e: Exception) {
            e.printStackTrace()
            return@withContext RemoteOcrResult(false, null, "Некорректный JSON ответа")
        }

        val status = obj.optString("status", "error")
        if (status != "ok") {
            val msg = obj.optString("message", "status != ok")
            return@withContext RemoteOcrResult(false, null, msg)
        }

        val dataObj = obj.optJSONObject("data")
            ?: return@withContext RemoteOcrResult(false, null, "Нет поля data")

        fun optStringOrNull(name: String): String? =
            dataObj.optString(name, "").takeIf { it.isNotBlank() }

        val parcel = OcrParcelData(
            trackingNo = optStringOrNull("tracking_no"),
            receiverCountryCode = optStringOrNull("receiver_country_code"),
            receiverName = optStringOrNull("receiver_name"),
            receiverAddress = null, // php пока не отдаёт
            receiverCompany = optStringOrNull("receiver_company"),
            receiverForwarderCode = optStringOrNull("receiver_forwarder_code"),
            receiverCellCode = optStringOrNull("receiver_cell_code"),
            // NEW:
            localCarrierName = optStringOrNull("local_carrier_name"),
            localTrackingNo  = optStringOrNull("local_tracking_no"),

            senderName = null,
            weightKg = null,
            sizeL = null,
            sizeW = null,
            sizeH = null
        )

        RemoteOcrResult(true, parcel, null)
    } catch (e: Exception) {
        e.printStackTrace()
        RemoteOcrResult(false, null, e.message ?: "Ошибка соединения")
    } finally {
        conn.disconnect()
    }
}

data class EnrollResult(
    val ok: Boolean,
    val deviceToken: String?,
    val errorMessage: String?
)

/**
 * HTTP POST на <server_url>/api/device_enroll.php
 */
suspend fun enrollDeviceOnServer(
    context: Context,
    cfg: DeviceConfig
): EnrollResult = withContext(Dispatchers.IO) {
    val baseUrl = cfg.serverUrl.trim()
    if (baseUrl.isEmpty()) {
        return@withContext EnrollResult(false, null, "Пустой Server URL")
    }

    val urlStr = baseUrl.trimEnd('/') + "/api/device_enroll.php"
    val url = URL(urlStr)
    val conn = (url.openConnection() as HttpURLConnection)

    // TLS – разрешаем самоподписанный сертификат, если включено в настройках
    if (conn is HttpsURLConnection && cfg.allowInsecureSsl) {
        val trustAllCerts = arrayOf<TrustManager>(
            object : X509TrustManager {
                override fun checkClientTrusted(
                    chain: Array<X509Certificate>,
                    authType: String
                ) { }

                override fun checkServerTrusted(
                    chain: Array<X509Certificate>,
                    authType: String
                ) { }

                override fun getAcceptedIssuers(): Array<X509Certificate> =
                    emptyArray()
            }
        )

        val sslContext = SSLContext.getInstance("TLS")
        sslContext.init(null, trustAllCerts, SecureRandom())
        conn.sslSocketFactory = sslContext.socketFactory

        conn.hostnameVerifier = HostnameVerifier { _: String?, _: SSLSession? ->
            true
        }
    }

    try {
        conn.requestMethod = "POST"
        conn.connectTimeout = 5000
        conn.readTimeout = 5000
        conn.doOutput = true
        conn.setRequestProperty("Content-Type", "application/json; charset=utf-8")

        // версия приложения
        val pm = context.packageManager
        val pInfo = pm.getPackageInfo(context.packageName, 0)
        val appVersion = pInfo.versionName ?: "unknown"

        // модель устройства
        val manufacturer = Build.MANUFACTURER ?: ""
        val model = Build.MODEL ?: ""
        val modelString = (manufacturer + " " + model).trim()

        val serial = ""

        val json = JSONObject().apply {
            put("mode", "enroll")
            put("device_uid", cfg.deviceUid)
            put("name", cfg.deviceName)
            put("serial", serial)
            put("model", modelString)
            put("app_version", appVersion)
        }

        val bodyBytes = json.toString().toByteArray(Charsets.UTF_8)
        conn.outputStream.use { os ->
            os.write(bodyBytes)
        }

        val code = conn.responseCode
        val stream = if (code in 200..299) conn.inputStream else conn.errorStream
        val respText = stream?.bufferedReader(Charsets.UTF_8)?.readText() ?: ""

        println("ENROLL resp code=$code body=$respText")

        if (respText.isBlank()) {
            return@withContext EnrollResult(false, null, "Пустой ответ сервера (HTTP $code)")
        }

        val obj = try {
            JSONObject(respText)
        } catch (e: Exception) {
            e.printStackTrace()
            return@withContext EnrollResult(false, null, "Некорректный JSON ответа")
        }

        val status = obj.optString("status", "error")
        if (status != "ok") {
            val msg = obj.optString(
                "message",
                obj.optString("messages", "status != ok")
            )
            return@withContext EnrollResult(false, null, msg)
        }

        val token = obj.optString("device_token", null)
        val isActive = obj.optInt("is_active", 0) // если нужно, можно где-то учитывать

        EnrollResult(true, token, null)
    } catch (e: Exception) {
        e.printStackTrace()
        EnrollResult(false, null, e.message ?: "Ошибка соединения")
    } finally {
        conn.disconnect()
    }
}

data class QrLoginResult(
    val ok: Boolean,
    val sessionId: String?,
    val errorMessage: String?
)
/**
 * Логин по QR:
 * POST /api/device_enroll.php
 * {
 *   "action": "qr_login",
 *   "device_uid":   "...",
 *   "device_token": "...",
 *   "qr_token":     "<строка из QR>",
 *   "app_version":  "1.0"
 * }
 */
suspend fun qrLoginOnServer(
    context: Context,
    cfg: DeviceConfig,
    qrToken: String
): QrLoginResult = withContext(Dispatchers.IO) {
    val baseUrl = cfg.serverUrl.trim()
    if (baseUrl.isEmpty()) {
        return@withContext QrLoginResult(false, null, "Пустой Server URL")
    }
    if (cfg.deviceToken.isNullOrBlank()) {
        return@withContext QrLoginResult(false, null, "Нет device_token (устройство не привязано)")
    }

    val urlStr = baseUrl.trimEnd('/') + "/api/device_enroll.php"
    val url = URL(urlStr)
    val conn = (url.openConnection() as HttpURLConnection)

    // TLS по-прежнему как у тебя:
    if (conn is HttpsURLConnection && cfg.allowInsecureSsl) {
        val trustAllCerts = arrayOf<TrustManager>(
            object : X509TrustManager {
                override fun checkClientTrusted(chain: Array<X509Certificate>, authType: String) {}
                override fun checkServerTrusted(chain: Array<X509Certificate>, authType: String) {}
                override fun getAcceptedIssuers(): Array<X509Certificate> = emptyArray()
            }
        )
        val sslContext = SSLContext.getInstance("TLS")
        sslContext.init(null, trustAllCerts, SecureRandom())
        conn.sslSocketFactory = sslContext.socketFactory
        conn.hostnameVerifier = HostnameVerifier { _: String?, _: SSLSession? -> true }
    }

    try {
        conn.requestMethod = "POST"
        conn.connectTimeout = 5000
        conn.readTimeout = 5000
        conn.doOutput = true
        conn.setRequestProperty("Content-Type", "application/json; charset=utf-8")

        val pm = context.packageManager
        val pInfo = pm.getPackageInfo(context.packageName, 0)
        val appVersion = pInfo.versionName ?: "unknown"

        val json = JSONObject().apply {
            put("mode", "qr_login")
            put("device_uid", cfg.deviceUid)
            put("device_token", cfg.deviceToken)
            put("qr_token", qrToken)
            put("app_version", appVersion)
        }

        val bodyBytes = json.toString().toByteArray(Charsets.UTF_8)
        conn.outputStream.use { it.write(bodyBytes) }

        val code = conn.responseCode
        val stream: InputStream? =
            if (code in 200..299) conn.inputStream else conn.errorStream

        val respText = stream
            ?.bufferedReader(Charsets.UTF_8)
            ?.use { it.readText() }
            ?: ""

        if (respText.isBlank()) {
            return@withContext QrLoginResult(false, null, "Пустой ответ сервера (HTTP $code)")
        }

        val obj = try {
            JSONObject(respText)
        } catch (e: Exception) {
            e.printStackTrace()
            return@withContext QrLoginResult(false, null, "Некорректный JSON ответа")
        }

        val status = obj.optString("status", "error")
        if (status != "ok") {
            val msg = obj.optString("message", "status != ok")
            return@withContext QrLoginResult(false, null, msg)
        }

        // сервер теперь отдаёт session_id + user_id + role
        val sessionId = obj.optString("session_id", null)
        if (sessionId.isNullOrBlank()) {
            return@withContext QrLoginResult(false, null, "session_id не вернулся от сервера")
        }

        QrLoginResult(true, sessionId, null)
    } catch (e: Exception) {
        e.printStackTrace()
        QrLoginResult(false, null, e.message ?: "Ошибка соединения")
    } finally {
        conn.disconnect()
    }
}

fun parseOcrTemplates(json: String): OcrTemplates? = try {
    val root = JSONObject(json)
    val version = root.optInt("version", 1)
    val carriersObj = root.optJSONObject("carriers") ?: return null

    val carriersMap = mutableMapOf<String, CarrierTemplate>()

    val carrierNames = carriersObj.keys()
    while (carrierNames.hasNext()) {
        val code = carrierNames.next()

        val carrierVal = carriersObj.opt(code)
        val cObj: JSONObject = when (carrierVal) {
            is JSONObject -> carrierVal
            is org.json.JSONArray -> carrierVal.optJSONObject(0) ?: continue
            else -> continue
        }


        val displayName = cObj.optString("display_name", code)
        val rulesObj = cObj.optJSONObject("rules") ?: JSONObject()

        val rulesMap = mutableMapOf<String, RuleConfig>()
        val ruleNames = rulesObj.keys()
        while (ruleNames.hasNext()) {
            val rk = ruleNames.next()
            val rObj = rulesObj.optJSONObject(rk) ?: continue

            val type = rObj.optString("type", "")
            if (type.isBlank()) continue

            val pattern = if (rObj.has("pattern")) rObj.optString("pattern") else null
            val lines = if (rObj.has("lines")) rObj.optInt("lines") else null

            val mvArr = rObj.optJSONArray("marker_variants")
            val markerVariants = mutableListOf<String>()
            if (mvArr != null) {
                for (i in 0 until mvArr.length()) markerVariants += mvArr.optString(i)
            }

            rulesMap[rk] = RuleConfig(
                type = type,
                pattern = pattern,
                markerVariants = markerVariants.takeIf { it.isNotEmpty() },
                lines = lines
            )
        }

        carriersMap[code] = CarrierTemplate(
            displayName = displayName,
            rules = rulesMap
        )
    }

    OcrTemplates(version = version, carriers = carriersMap)
} catch (e: Exception) {
    e.printStackTrace()
    null
}

@Composable
fun DeviceWebViewScreen(
    config: DeviceConfig,
    onWebViewReady: (WebView) -> Unit,
    onSessionEnded: () -> Unit,
    modifier: Modifier = Modifier,
    onTemplatesLoaded: (OcrTemplates?) -> Unit,
    onNameDictLoaded: (NameDict?) -> Unit,   // <<< НОВЫЙ колбэк
    onContextUpdated: (taskJson: String?, tmplJson: String?, destJson: String?, dictJson: String?) -> Unit

) {
    AndroidView(
        modifier = modifier.fillMaxSize(),
        factory = { ctx ->
            WebView(ctx).apply {
                val mainHandler = Handler(Looper.getMainLooper())

                class DeviceBridge {
                    @JavascriptInterface
                    fun onMainContext(payload: String?) {
                        if (payload.isNullOrBlank()) return
                        try {
                            val obj = JSONObject(payload)
                            val task = obj.optString("task", "").takeIf { it.isNotBlank() }
                            val tmpl = obj.optString("ocr_templates", "").takeIf { it.isNotBlank() }
                            val dest = obj.optString("destcountry", "").takeIf { it.isNotBlank() }
                            val dict = obj.optString("dicts", "").takeIf { it.isNotBlank() }

                            mainHandler.post {
                                fun clean(v: String?): String? {
                                    val s = v?.trim()
                                    if (s.isNullOrEmpty()) return null
                                    if (s.equals("null", true) || s.equals("undefined", true)) return null
                                    return s
                                }

                                val task = clean(obj.optString("task", null))
                                val tmpl = clean(obj.optString("ocr_templates", null))
                                val dest = clean(obj.optString("destcountry", null))
                                val dict = clean(obj.optString("dicts", null))
                                onContextUpdated(task, tmpl, dest, dict)
                            }
                        } catch (_: Exception) {}
                    }
                }

                addJavascriptInterface(DeviceBridge(), "DeviceApp")
                layoutParams = ViewGroup.LayoutParams(
                    ViewGroup.LayoutParams.MATCH_PARENT,
                    ViewGroup.LayoutParams.MATCH_PARENT
                )

                settings.javaScriptEnabled = true
                settings.domStorageEnabled = true

                webViewClient = object : WebViewClient() {

                    private var firstPageLoaded = false

                    override fun onReceivedSslError(
                        view: WebView?,
                        handler: SslErrorHandler?,
                        error: SslError?
                    ) {
                        // как было раньше: игнорируем SSL, если включен флаг
                        if (config.allowInsecureSsl) {
                            handler?.proceed()
                        } else {
                            handler?.cancel()
                        }
                    }

                    override fun onPageFinished(view: WebView?, url: String?) {
                        super.onPageFinished(view, url)

                        if (!firstPageLoaded && view != null) {
                            firstPageLoaded = true
                            onWebViewReady(view)
                          ////  view.evaluateJavascript(INSTALL_MAIN_OBSERVER_JS, null)
                        }
                        // ВАЖНО: всегда инжектим, потому что при реальном reload JS улетает
                        view?.evaluateJavascript(INSTALL_MAIN_OBSERVER_JS, null)

                        val base = normalizeServerUrl(config.serverUrl)
                        if (!url.isNullOrBlank() && base.isNotBlank()) {
                            val u = Uri.parse(url)
                            val b = Uri.parse(base)

                            val sameHost = (u.scheme == b.scheme) && (u.host == b.host) && (u.port == b.port)
                            val path = u.path ?: ""

                            // считаем "сессия закончилась" только если реально открылась страница логина
                            if (sameHost && (path == "/login" || path.startsWith("/login/"))) {
                                onSessionEnded()
                            }
                        }
                    }
                }

                val base = normalizeServerUrl(config.serverUrl)
                val startUrl = if (base.isNotEmpty()) "$base/main" else "about:blank"
                loadUrl(startUrl)
                // ВАЖНО: onWebViewReady здесь НЕ вызываем, только в onPageFinished
            }
        }
    )
}

data class DestForwarder(
    val code: String,
    val name: String,
    val aliases: List<String>
)

data class DestCountryCfg(
    val id: Int,
    val code_iso2: String,
    val code_iso3: String,
    val name_en: String,
    val name_local: String,
    val aliases: List<String>,
    val forwarders: List<DestForwarder>
)

data class DetectedDest(
    val countryCode: String?,
    val countryName: String?,
    val forwarderCode: String?,
    val forwarderName: String?
)


// ---- OCR-шаблоны, которые прилетают с сервера через скрытый div ----

data class OcrTemplates(
    val version: Int,
    val carriers: Map<String, CarrierTemplate>
)

data class CarrierTemplate(
    val displayName: String,
    val rules: Map<String, RuleConfig>
)

data class RuleConfig(
    val type: String,
    val pattern: String? = null,
    val markerVariants: List<String>? = null,
    val lines: Int? = null
)



data class NameDict(
    val version: Int,
    val exactBad: List<String>,
    val substrBad: List<String>
)

fun defaultNameDict(): NameDict = NameDict(
    version = 1,
    exactBad = listOf(
        "llc","gmbh","gimbh","cmr",
        "telefon","kontakt","datum",
        "paketschein","paket","fremdbarcode",
        "time","weight","de","d","co2","kg","kg paket",
        "absender","absenderin/sender",
        "postleitzahl","postleitzanl","postleitzah","postieitzah","pastleitzahl",
        "day","dhl","phl","ed","h&m",
        "mainz","hamm","nürtingen","nuertingen","leitcode routingcode",
        "rack","gusensberg",
        "vus chland + eu","vuschland + eu","vuschland+eu","vuschland",
        "herrn","llg",
        "desc, cosmetics","desc","cosmetics",
        "expres","cos",
        "hermes","hhermes","ghermnes",
        "xun","|am","|an","|an:",
        "fron ce","do not use for returns","postage paid",
        "puma","ainz","koli",
        "revolution beauty","sunday natural products",
        "paket nr","frmeswe do cg",
        "apo pharmacy b.v","apo pharmacy b v",
        "kunden nr","gewicht in kg",
        "billing no","yor gls track",
        "cust id","customer id",
        "ioerffl deh holldore",
        "service sperrgut aencorbrant","service sperrgut","sperrgut",
        "fedex aerm eny",
        "cho gxo supply chain","co gxo supply chain",
        "koliexp","orthopädie geld","we lg h","contsct",
        "delivery address","deiivery address",
        "entglt ezaht","mehr kommfort ein","dror code"
    ),
    substrBad = listOf(
        " llc"," gmbh"," gimbh","gm bh",
        " online"," shop"," lounge"," hub",
        " paket","päckchen","paketschein"," gewicht","anzahl",
        " datum","kontakt"," telefon","contsct",
        "postleit","fremdbarcode","id no","cust.","customer",
        "shipment","sendungs","abrechnungsnr","referenznr","ref.",
        "epg one","co2","emiss","we lg h",
        "starkenburgstr","starkenburgstraße"," str.","straße","strasse",
        "deutschland","germany","hessen",
        "mörfelden","morfelden","harfelden","nharfelden","walldorf","wal ldorf","unna",
        "kg paket","ups s tandard","ups standard","dp ag",
        "warenpost","parcel connect",
        "billing p/p","biling. p/p","biling p/p","billing no"," billing",
        "wir reduzierer","pl ieutschiond",
        "wir kompensieren","wir kompens","wir kormip","wir kormi",
        "labex","l.t.d",
        "empfanger","empfänger","sender","leitcode routingcode",
        "online - shop","ioerffl deh holldore",
        "veepee","best secret","best secrel","dhl hub",
        "inditex"," zara","zalando lounge","zalando se","zalando ",
        "deutsche post","post dhl hub","|am",
        "c/o deutsche post","c/o dhl","c/o ",
        "nürtingen","nuertingen","mainz","hamm","gusensberg",
        " vound nachname","vor- und nachname","nachname",
        " gewicht","gew'cht","gew.cht","do not use for returns",
        " empf nger","empf nger","empfi nger","empfaenger","empfänger",
        " kundenreferenz","notiz",
        " orthopädie","orthopädie-geld",
        " poing","potsdam","krefeld","magdeburg","neum ark","neumark",
        " @rmany"," germany","deutschland",
        "kunden nr","paket nr","frmeswe do cg",
        "asos"," we do","we-do","ve do","ve do!",
        "es naalbaceie",
        "revolution beauty"," beauty",
        "sunday natural products"," natural products"," products",
        "inklusive nachhaltigem versand"," nachhaltigem versand"," versand",
        "apo pharmacy"," pharmacy",
        "autosevice baudisch","autosevice","baudisch",
        "gxo supply chain"," supply chain",
        "rel nasee. ret","rel nasee","destnstire","orthopädie geld",
        "absen der","koliexp","yor gls track",
        "gewicht in kg","|an","|an:","fron ce",
        "apo pharmacy b.v","apo pharmacy b v",
        "service sperrgut aencorbrant","service sperrgut","sperrgut",
        "inklusive nachhaltigem versand",
        "billing no",
        "desc, cosmetics","desc","cosmetics",
        "cust id","mehr kommfort ein","postage paid",
        "empfång","entglt","entgelt",
        "dror code","fedex aerm eny",
        "siehe rückseite","unter dhl.de","mit dhl"
    )
)

fun parseNameDictJson(json: String): NameDict? {
    return try {
        val obj = JSONObject(json)

        val version = obj.optInt("version", 1)

        fun readArray(name: String): List<String> {
            val arr = obj.optJSONArray(name) ?: return emptyList()
            val res = mutableListOf<String>()
            for (i in 0 until arr.length()) {
                val v = arr.optString(i, "").trim()
                if (v.isNotEmpty()) {
                    res += v.lowercase()
                }
            }
            return res
        }

        NameDict(
            version = version,
            exactBad = readArray("exact_bad"),
            substrBad = readArray("substr_bad")
        )
    } catch (e: Exception) {
        e.printStackTrace()
        null
    }
}

data class OcrParcelData(
    val tuid: String? = null,              // <--- NEW
    val trackingNo: String? = null,

    // ISO2, например "AZ", "DE", "KG"
    val receiverCountryCode: String? = null,

    val receiverName: String? = null,
    val receiverAddress: String? = null,

    // компания-форвардер (Camex, Colibri Express и т.д.)
    val receiverCompany: String? = null,

    // машинный код форвардера (CAMEX, COLIBRI, ASER …)
    val receiverForwarderCode: String? = null,

    // номер ячейки A66050 / AS228905 / C163361 …
    val receiverCellCode: String? = null,

    val senderName: String? = null,
    val weightKg: Double? = null,
    val sizeL: Double? = null,
    val sizeW: Double? = null,
    val sizeH: Double? = null,

    // NEW: локальный перевозчик (DHL/GLS/HERMES/UPS/AMAZON) и его трек
    val localCarrierName: String? = null,
    val localTrackingNo: String? = null

)

fun fillBarcodeUsingTemplate(webView: WebView, barcodeValue: String, action: ScanAction?) {
    if (action?.action != "fill_field") return
    val field = action.fieldId?.takeIf { it.isNotBlank() }
        ?: action.fieldName?.takeIf { it.isNotBlank() }
        ?: return

    fun esc(str: String): String =
        str.replace("\\", "\\\\")
            .replace("'", "\\'")
            .replace("\n", " ")
            .replace("\r", " ")

    val js = """
        (function(){
          function fill(id){
            var el=document.getElementById(id) || document.querySelector('[name=""+id+""]');
            if(!el) return;
            el.value='${esc(barcodeValue)}';
            el.dispatchEvent(new Event('input',{bubbles:true}));
            el.dispatchEvent(new Event('change',{bubbles:true}));
          }
          fill('${esc(field)}');
        })();
    """.trimIndent()

    webView.post { webView.evaluateJavascript(js, null) }
}

fun requestStandMeasurementInWebView(webView: WebView) {
    val js = "if (window.requestStandMeasurement) { window.requestStandMeasurement(); }"
    webView.post { webView.evaluateJavascript(js, null) }
}

fun withStandDeviceSelected(webView: WebView, onResult: (Boolean) -> Unit) {
    val js = """
        (function(){
          var el = document.getElementById('standDevice');
          return !!(el && el.value);
        })();
    """.trimIndent()
    webView.post {
        webView.evaluateJavascript(js) { raw ->
            val normalized = raw?.trim()?.trim('"')?.lowercase()
            onResult(normalized == "true")
        }
    }
}

fun fillParcelFormInWebView(webView: WebView, data: OcrParcelData) {
    println("### fillParcelFormInWebView() data = $data")

    fun esc(str: String): String =
        str.replace("\\", "\\\\")
            .replace("'", "\\'")
            .replace("\n", " ")
            .replace("\r", " ")

    val forwarderCode = data.receiverForwarderCode?.trim().takeUnless { it.isNullOrEmpty() }  // CAMEX/KOLLI/...
    val forwarderName = data.receiverCompany?.trim().takeUnless { it.isNullOrEmpty() }       // Camex/KoliExpress/...
    val cellCode      = data.receiverCellCode?.trim().takeUnless { it.isNullOrEmpty() }      // A176903
    val forwarderCodeForSelect = forwarderCode
        ?: forwarderName?.let { detectForwarderByText(it) }
        ?: cellCode?.let { detectForwarderByCellCode(it) }
    val forwarderDisplayName = forwarderCodeForSelect?.let { forwarderCompanyByCode(it) } ?: forwarderName
    val receiverName  = data.receiverName?.trim().takeUnless { it.isNullOrEmpty() }          // SEVDA Farzaliyeva


    val countryForSelect = normalizeCountryForSelect(data.receiverCountryCode)               // AZ/GE/KG/DE

    val localCarrier = data.localCarrierName?.trim()?.takeIf { it.isNotEmpty() }       // DHL/GLS/...
    val trackingForForm = data.localTrackingNo?.trim()?.takeIf { it.isNotEmpty() }
            ?: data.trackingNo?.trim()?.takeIf { isProbableTrackingNo(it) }
            ?: data.tuid?.trim()?.takeIf { it.isNotEmpty() }

    // подпись рядом с TUID: показываем именно форвард/страну (не локального перевозчика)
    val carrierInfo = buildString {
        if (!forwarderCodeForSelect.isNullOrBlank()) append(forwarderCodeForSelect)
        if (!forwarderDisplayName.isNullOrBlank()) {
            if (isNotEmpty()) append(" / ")
            append(forwarderDisplayName)
        }
        if (!countryForSelect.isNullOrBlank()) {
            if (isNotEmpty()) append(" / ")
            append(countryForSelect)
        }
    }

    val js = buildString {
        append("(function(){")
        append(
            """
            function setValById(id,v){
              var e=document.getElementById(id);
              if(e){
                e.value=v;
                e.dispatchEvent(new Event('input',{bubbles:true}));
                e.dispatchEvent(new Event('change',{bubbles:true}));
              }
            }
                        function setValIfEmpty(id,v){
                          var e=document.getElementById(id);
                          if(e){
                            var isEmpty = !e.value || e.value.trim()==='';
                            if(!isEmpty) return;
                            e.value=v;
                            e.dispatchEvent(new Event('input',{bubbles:true}));
                            e.dispatchEvent(new Event('change',{bubbles:true}));
                          }
                        }
            function setValByName(name,v){
              var e=document.querySelector('[name="'+name+'"]');
              if(e){
                e.value=v;
                e.dispatchEvent(new Event('input',{bubbles:true}));
                e.dispatchEvent(new Event('change',{bubbles:true}));
              }
            }
            function setSelectVal(id,v){
              var e=document.getElementById(id);
              if(e){
                e.value=v;
                e.dispatchEvent(new Event('change',{bubbles:true}));
              }
            }
            function setText(id,text){
              var e=document.getElementById(id);
              if(e){ e.textContent=text; }
            }
            """.trimIndent()
        )

            // писать в tuid/trackingNo только если это реально похоже на трек
// 1) TUID — только если нашли
        //data.tuid?.trim()?.takeIf { it.isNotEmpty() }?.let {
        //    append("setValById('tuid','${esc(it)}');")
        //}
        val tuidForForm = data.tuid?.trim()?.takeIf { it.isNotEmpty() } ?: trackingForForm
        tuidForForm?.let { append("setValIfEmpty('tuid','${esc(it)}');") }
        // 2) trackingNo — DHL/UPS/etc (если нашли), иначе обычный trackingNo (если похож)
        val trackingFieldValue = trackingForForm ?: tuidForForm
        trackingFieldValue?.let {
            append("setValIfEmpty('trackingNo','${esc(it)}');")
        }

        // 2) carrierName (локальный перевозчик: DHL/GLS/HERMES/UPS/AMAZON)
        // carrierName — можно отдельно
        localCarrier?.let {
            val v = esc(it)
            append("setValById('carrierName','$v');")

        }

        // 3) receiverCountry (по назначению) — строго ISO2 из select
        countryForSelect?.let {
            //append("setSelectVal('receiverCountry','${esc(it)}');")
            append("setSelectVal('receiverCountry','${esc(it)}');")
        }

        // 4) receiverCompany — ТОЛЬКО название компании форварда
        // 5) carrierCode (id=carrierCode) — ТОЛЬКО код форварда + селект форварда
        forwarderCodeForSelect?.let {
            val v = esc(it)
            append("setSelectVal('receiverCompany','$v');")
            append("setValById('carrierCode','$v');")
        } ?: forwarderCode?.let {
            append("setValById('carrierCode','${esc(it)}');")
        }

        // 6) receiverName — ТОЛЬКО получатель
        receiverName?.let {
            append("setValById('receiverName','${esc(it)}');")
        }

        // 7) receiverAddress — ТОЛЬКО ячейка
        cellCode?.let {
            append("setValById('receiverAddress','${esc(it)}');")
        }

        // 8) подпись рядом с TUID
        if (carrierInfo.isNotBlank()) {
            append("setText('ocrCarrierInfo',' (${esc(carrierInfo)})');")
        } else {
            append("setText('ocrCarrierInfo','');")
        }

        // 9) вес/габариты — НЕ трогаем, если null (и НЕ пишем нули)
        data.weightKg?.let { append("setValById('weightKg','${it}');") }
        data.sizeL?.let   { append("setValById('sizeL','${it}');") }
        data.sizeW?.let   { append("setValById('sizeW','${it}');") }
        data.sizeH?.let   { append("setValById('sizeH','${it}');") }

        append("})();")
    }

    webView.post { webView.evaluateJavascript(js, null) }
}


fun buildParcelFromBarcode(raw: String, sanitizeInput: Boolean = true): OcrParcelData {
    val clean = if (sanitizeInput) sanitizeBarcodeInput(raw) else raw
    val carrier = detectLocalCarrierName(clean)
    return OcrParcelData(
        tuid = clean,
        trackingNo = clean,
        localCarrierName = carrier
    )
}


fun sanitizeBarcodeInput(raw: String): String {
    var s = raw.trim()

    // Отбрасываем префиксы вида "]C1" / "]E0" (символика штрихкода)
    val prefix = Regex("^\\][A-Za-z]\\d")
    prefix.find(s)?.let { match ->
        s = s.removePrefix(match.value)
    }

    // чистим неалфавитно-цифровые символы по краям
    s = s.trim { !it.isLetterOrDigit() }

    // выбираем самую длинную подходящую алфавитно-цифровую последовательность (8+)
    val best = Regex("[A-Za-z0-9]{8,}").findAll(s).maxByOrNull { it.value.length }?.value

    return (best ?: s).trim()
}


fun detectForwarderByCellCode(cellRaw: String): String? {
    val cell = cellRaw.trim().uppercase()

    return when {
        Regex("^(KL|KI)\\d+$").matches(cell) -> "KOLLI"
        Regex("^C\\d+$").matches(cell) || Regex("^S\\d+$").matches(cell) || Regex("^OP\\d+$").matches(cell) -> "COLIBRI"
        Regex("^AS\\d+$").matches(cell) -> "ASER"
        Regex("^PL\\d+$").matches(cell) -> "POSTLINK"
        Regex("^A\\d+$").matches(cell) -> "CAMEX"
        Regex("^FX\\d+$").matches(cell) -> "KARGOFLEX"
        Regex("^B\\d+$").matches(cell) || Regex("^K\\d+$").matches(cell) -> "CAMARATC"
        else -> null
    }
}

fun forwarderCompanyByCode(code: String): String = when (code) {
    "COLIBRI"   -> "Colibri Express"
    "KOLLI"     -> "KoliExpress"
    "ASER"      -> "ASER Express"
    "CAMEX"     -> "Camex"
    "KARGOFLEX" -> "KargoFlex"
    "CAMARATC"  -> "Camaratc"
    "POSTLINK"  -> "Postlink"
    else -> code
}

fun defaultCountryIso2ByForwarder(code: String, fullText: String): String? {
    val t = fullText.lowercase()

    return when (code) {
        "COLIBRI","KOLLI","ASER","CAMEX","KARGOFLEX","POSTLINK" -> "AZ"
        "CAMARATC" -> when {
            t.contains("starkenburgstr.10b") || t.contains("starkenburgstr 10b") || t.contains("starkenburgstr10b") -> "GE"
            t.contains("starkenburgstr.10e") || t.contains("starkenburgstr 10e") || t.contains("starkenburgstr10e") -> "KG"
            else -> null
        }
        else -> null
    }
}

fun isProbableTrackingNo(v: String?): Boolean {
    val s = v?.trim()?.replace(" ", "")?.uppercase() ?: return false
    if (s.length < 9) return false
    if (!s.matches(Regex("^[A-Z0-9\\-]+$"))) return false

    val digitCount = s.count { it.isDigit() }
    if (digitCount < 8) return false   // режет “64546HESSEN”

    return true
}
fun parseOcrText(text: String): OcrParcelData {
    // Разбиваем на строки
    val allLines = text.lines()
    val lines = allLines
        .map { it.trim() }
        .filter { it.isNotEmpty() }

    // ===== ТРЕК =====
    // Длинная строка из латиницы/цифр/дефисов
    // ===== ТРЕК =====
// хотя бы 1 цифра, только A-Z0-9-, длина >= 10
    val trackingCandidate = lines.firstOrNull { isProbableTrackingNo(it) }
    // ===== ПОЛУЧАТЕЛЬ ПО МАРКЕРУ "an:" =====
    var receiverName: String? = null
    var receiverAddress: String? = null

    val idxAn = lines.indexOfFirst { line ->
        val low = line.lowercase()
        low.startsWith("an:") || low.startsWith("an ")
    }

    if (idxAn >= 0) {
        val line = lines[idxAn]
        val low = line.lowercase()

        val anPos = low.indexOf("an")
        var after = line.substring(anPos + 2) // после "an"
            .trim()

        if (after.startsWith(":")) {
            after = after.drop(1).trim()
        }

        if (after.isNotBlank()) {
            // "An: Max Mustermann"
            receiverName = after
            receiverAddress = lines.getOrNull(idxAn + 1)
        } else {
            // "An:" на одной строке, имя и адрес ниже
            receiverName = lines.getOrNull(idxAn + 1)
            receiverAddress = lines.getOrNull(idxAn + 2)
        }
    }

    // ===== ВЕС =====
    val weightRegex = Regex("(\\d+[\\.,]\\d*)\\s*(kg|кг)", RegexOption.IGNORE_CASE)
    val weightMatch = weightRegex.find(text)
    val weightKg = weightMatch
        ?.groups?.get(1)?.value
        ?.replace(',', '.')
        ?.toDoubleOrNull()

    // ===== ГАБАРИТЫ =====
    val sizeRegex = Regex(
        "(\\d+(?:[\\.,]\\d*)?)\\s*[xXх]\\s*(\\d+(?:[\\.,]\\d*)?)\\s*[xXх]\\s*(\\d+(?:[\\.,]\\d*)?)"
    )
    val sizeMatch = sizeRegex.find(text)
    val (sizeL, sizeW, sizeH) =
        if (sizeMatch != null) {
            val lStr = sizeMatch.groupValues[1].replace(',', '.')
            val wStr = sizeMatch.groupValues[2].replace(',', '.')
            val hStr = sizeMatch.groupValues[3].replace(',', '.')
            Triple(
                lStr.toDoubleOrNull(),
                wStr.toDoubleOrNull(),
                hStr.toDoubleOrNull()
            )
        } else {
            Triple(null, null, null)
        }

    return OcrParcelData(
        trackingNo            = trackingCandidate,
        receiverCountryCode   = null,   // страну пока не трогаем, это будет через destConfig
        receiverName          = receiverName,
        receiverAddress       = receiverAddress,
        receiverCompany       = null,
        receiverForwarderCode = null,
        receiverCellCode      = null,
        senderName            = null,
        weightKg              = weightKg,
        sizeL                 = sizeL,
        sizeW                 = sizeW,
        sizeH                 = sizeH
    )
}


fun clearParcelFormInWebView(webView: WebView) {
    val js = """
        (function(){
          function setValById(id,v){
            var e=document.getElementById(id);
            if(e){
              e.value=v;
              e.dispatchEvent(new Event('input',{bubbles:true}));
              e.dispatchEvent(new Event('change',{bubbles:true}));
            }
          }
          function setValByName(name,v){
            var e=document.querySelector('[name="'+name+'"]');
            if(e){
              e.value=v;
              e.dispatchEvent(new Event('input',{bubbles:true}));
              e.dispatchEvent(new Event('change',{bubbles:true}));
            }
          }
          function setText(id,text){
            var e=document.getElementById(id);
            if(e){ e.textContent=text; }
          }

          setValById('tuid','');
          setValById('trackingNo','');

          setValById('carrierName','');
          setValByName('carrierCode','');     // hidden name="carrierCode"

          setValById('receiverCountry','');   // если нет пустого option — визуально может не сброситься
          setValById('receiverName','');
          setValById('receiverAddress','');
          setValById('receiverCompany','');

          setValById('carrierCode','');       // <-- ВАЖНО: форвард CODE именно тут

          setValById('weightKg','');
          setValById('sizeL','');
          setValById('sizeW','');
          setValById('sizeH','');

          setText('ocrCarrierInfo','');
        })();
    """.trimIndent()

    webView.post { webView.evaluateJavascript(js, null) }
}

fun clearTrackingAndTuidInWebView(webView: WebView) {
    val js = """
        (function(){
          function setValById(id,v){
            var e=document.getElementById(id);
            if(e){
              e.value=v;
              e.dispatchEvent(new Event('input',{bubbles:true}));
              e.dispatchEvent(new Event('change',{bubbles:true}));
            }
          }

          setValById('tuid','');
          setValById('trackingNo','');
        })();
    """.trimIndent()

    webView.post { webView.evaluateJavascript(js, null) }
}

fun clearMeasurementsInWebView(webView: WebView) {
    val js = """
        (function(){
          function setValById(id,v){
            var e=document.getElementById(id);
            if(e){
              e.value=v;
              e.dispatchEvent(new Event('input',{bubbles:true}));
              e.dispatchEvent(new Event('change',{bubbles:true}));
            }
          }

          setValById('weightKg','');
          setValById('sizeL','');
          setValById('sizeW','');
          setValById('sizeH','');
        })();
    """.trimIndent()

    webView.post { webView.evaluateJavascript(js, null) }
}


fun clearParcelFormExceptTrack(webView: WebView) {
    val js = """
        (function(){
          function setValById(id,v){
            var e=document.getElementById(id);
            if(e){
              e.value=v;
              e.dispatchEvent(new Event('input',{bubbles:true}));
              e.dispatchEvent(new Event('change',{bubbles:true}));
            }
          }
          function setValByName(name,v){
            var e=document.querySelector('[name="'+name+'"]');
            if(e){
              e.value=v;
              e.dispatchEvent(new Event('input',{bubbles:true}));
              e.dispatchEvent(new Event('change',{bubbles:true}));
            }
          }
          function setText(id,text){
            var e=document.getElementById(id);
            if(e){ e.textContent=text; }
          }

          // не трогаем tuid и trackingNo
          setValById('carrierName','');
          setValByName('carrierCode','');     // hidden name="carrierCode"

          setValById('receiverCountry','');
          setValById('receiverName','');
          setValById('receiverAddress','');
          setValById('receiverCompany','');

          setValById('carrierCode','');

          setValById('weightKg','');
          setValById('sizeL','');
          setValById('sizeW','');
          setValById('sizeH','');

          setText('ocrCarrierInfo','');
        })();
    """.trimIndent()

    webView.post { webView.evaluateJavascript(js, null) }
}




fun prepareFormForNextScanInWebView(webView: WebView) {
    val js = """
        (function(){
          function getVal(id){
            var e=document.getElementById(id);
            return e ? (e.value||'').trim() : '';
          }
          function setValById(id,v){
            var e=document.getElementById(id);
            if(e){
              e.value=v;
              e.dispatchEvent(new Event('input',{bubbles:true}));
              e.dispatchEvent(new Event('change',{bubbles:true}));
            }
          }
          function setValByName(name,v){
            var e=document.querySelector('[name="'+name+'"]');
            if(e){
              e.value=v;
              e.dispatchEvent(new Event('input',{bubbles:true}));
              e.dispatchEvent(new Event('change',{bubbles:true}));
            }
          }
          function setText(id,text){
            var e=document.getElementById(id);
            if(e){ e.textContent=text; }
          }

          var tuid  = getVal('tuid');
          var track = getVal('trackingNo');

          if (tuid || track) {
            var btn = document.querySelector('button.js-core-link[data-core-action="add_new_item_in"]');
            if (btn) btn.click();

            setValById('tuid','');
            setValById('trackingNo','');

            setValById('carrierName','');
            setValByName('carrierCode','');

            setValById('receiverCountry','');
            setValById('receiverName','');
            setValById('receiverAddress','');
            setValById('receiverCompany','');

            setValById('carrierCode',''); // форвард CODE

            setValById('weightKg','');
            setValById('sizeL','');
            setValById('sizeW','');
            setValById('sizeH','');

            setText('ocrCarrierInfo','');
          }
        })();
    """.trimIndent()

    webView.post { webView.evaluateJavascript(js, null) }
}

@Composable
fun BarcodeScanScreen(
    modifier: Modifier = Modifier,
    onResult: (String) -> Unit,
    onCancel: () -> Unit,
    onBindHardwareTrigger: ( (()->Unit)? ) -> Unit,
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current

    var hasCameraPermission by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(
                context,
                Manifest.permission.CAMERA
            ) == PackageManager.PERMISSION_GRANTED
        )
    }

    val permissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission(),
        onResult = { granted -> hasCameraPermission = granted }
    )

    LaunchedEffect(Unit) {
        if (!hasCameraPermission) {
            permissionLauncher.launch(Manifest.permission.CAMERA)
        }
    }

    // CameraX
    val previewView = remember {
        PreviewView(context).apply {
            scaleType = PreviewView.ScaleType.FILL_CENTER
        }
    }

    val imageCapture = remember {
        ImageCapture.Builder()
            .setCaptureMode(ImageCapture.CAPTURE_MODE_MINIMIZE_LATENCY)
            .build()
    }

    var errorText by remember { mutableStateOf<String?>(null) }
    var isProcessing by remember { mutableStateOf(false) }

    fun captureAndScan() {
        if (isProcessing) return

        errorText = null
        isProcessing = true

        val executor = ContextCompat.getMainExecutor(context)
        imageCapture.takePicture(executor, object : ImageCapture.OnImageCapturedCallback() {
            override fun onCaptureSuccess(image: ImageProxy) {
                try {
                    val mediaImage = image.image
                    if (mediaImage == null) {
                        errorText = "Нет данных изображения"
                        return
                    }

                    val inputImage = InputImage.fromMediaImage(
                        mediaImage,
                        image.imageInfo.rotationDegrees
                    )

                    val options = BarcodeScannerOptions.Builder()
                        .setBarcodeFormats(
                            Barcode.FORMAT_CODE_128,
                            Barcode.FORMAT_CODE_39,
                            Barcode.FORMAT_CODE_93,
                            Barcode.FORMAT_CODABAR,
                            Barcode.FORMAT_EAN_8,
                            Barcode.FORMAT_EAN_13,
                            Barcode.FORMAT_UPC_A,
                            Barcode.FORMAT_UPC_E,
                            Barcode.FORMAT_QR_CODE
                        )
                        .build()

                    val scanner = BarcodeScanning.getClient(options)
                    scanner.process(inputImage)
                        .addOnSuccessListener { codes ->
                            val raw = codes.firstOrNull()?.rawValue
                            if (raw.isNullOrBlank()) {
                                errorText = "Штрихкод не найден"
                            } else {
                                onResult(raw)
                            }
                        }
                        .addOnFailureListener { e ->
                            errorText = "Ошибка сканера: ${e.message}"
                        }
                        .addOnCompleteListener {
                            isProcessing = false
                            image.close()
                        }
                } catch (e: Exception) {
                    errorText = "Сбой камеры"
                    isProcessing = false
                    image.close()
                }
            }

            override fun onError(exception: ImageCaptureException) {
                errorText = "Ошибка камеры: ${exception.message}"
                isProcessing = false
            }
        })
    }

    DisposableEffect(hasCameraPermission) {
        if (hasCameraPermission) {
            onBindHardwareTrigger { captureAndScan() }
        } else {
            onBindHardwareTrigger(null)
        }

        onDispose {
            onBindHardwareTrigger(null)
        }
    }

    LaunchedEffect(hasCameraPermission) {
        if (!hasCameraPermission) return@LaunchedEffect

        val cameraProviderFuture = ProcessCameraProvider.getInstance(context)
        val cameraProvider = cameraProviderFuture.get()

        val preview = Preview.Builder()
            .build()
            .also { it.setSurfaceProvider(previewView.surfaceProvider) }

        val selector = CameraSelector.DEFAULT_BACK_CAMERA

        try {
            cameraProvider.unbindAll()
            cameraProvider.bindToLifecycle(
                lifecycleOwner,
                selector,
                preview,
                imageCapture
            )
        } catch (e: Exception) {
            errorText = "Не удалось открыть камеру"
        }
    }

    Box(modifier = modifier.fillMaxSize()) {
        if (!hasCameraPermission) {
            Column(
                modifier = Modifier
                    .align(Alignment.Center)
                    .padding(16.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text("Нет доступа к камере")
                Spacer(Modifier.height(16.dp))
                OutlinedButton(onClick = onCancel) {
                    Text("Назад")
                }
            }
        } else {
            AndroidView(
                modifier = Modifier.fillMaxSize(),
                factory = { previewView }
            )

            Column(
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .fillMaxWidth()
                    .padding(16.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                errorText?.let {
                    Text(it, color = MaterialTheme.colorScheme.error)
                    Spacer(Modifier.height(8.dp))
                }

                if (isProcessing) {
                    Text("Сканирование…")
                }

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    OutlinedButton(onClick = onCancel, enabled = !isProcessing) {
                        Text("Отмена")
                    }

                    Button(onClick = { captureAndScan() }, enabled = !isProcessing) {
                        Text("BarScann")
                    }
                }
            }
        }
    }
}

@Composable
fun OcrScanScreen(
    modifier: Modifier = Modifier,
    destConfig: List<DestCountryCfg>,          // ДОБАВЛЕНО
    config: DeviceConfig,
    nameDict: NameDict?,                         // <<< НОВОЕ
    isDefaultMode: Boolean,
    onResult: (OcrParcelData) -> Unit,
    onCancel: () -> Unit,
    onBindHardwareTrigger: ((() -> Unit)?) -> Unit,
    onBarcodeClick: (() -> Unit)? = null,
    onBpClick: (() -> Unit)? = null,
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val scope = rememberCoroutineScope()

    // ===== Разрешение камеры =====
    var hasCameraPermission by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(
                context,
                Manifest.permission.CAMERA
            ) == PackageManager.PERMISSION_GRANTED
        )
    }

    val permissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission()
    ) { granted ->
        hasCameraPermission = granted
    }

    LaunchedEffect(Unit) {
        if (!hasCameraPermission) {
            permissionLauncher.launch(Manifest.permission.CAMERA)
        }
    }

    // ===== CameraX: превью + ImageCapture =====
    val previewView = remember {
        PreviewView(context).apply {
            scaleType = PreviewView.ScaleType.FILL_CENTER
        }
    }

    val imageCapture = remember {
        ImageCapture.Builder()
            .setCaptureMode(ImageCapture.CAPTURE_MODE_MINIMIZE_LATENCY)
            .build()
    }

    val executor = remember { Executors.newSingleThreadExecutor() }

    val recognizer = remember {
        TextRecognition.getClient(TextRecognizerOptions.DEFAULT_OPTIONS)
    }

    var isProcessing by remember { mutableStateOf(false) }
    var errorText by remember { mutableStateOf<String?>(null) }

    fun startCamera() {
        val cameraProviderFuture = ProcessCameraProvider.getInstance(context)
        val cameraProvider = cameraProviderFuture.get()

        val preview = Preview.Builder()
            .build()
            .also { it.setSurfaceProvider(previewView.surfaceProvider) }

        val selector = CameraSelector.DEFAULT_BACK_CAMERA

        try {
            cameraProvider.unbindAll()
            cameraProvider.bindToLifecycle(
                lifecycleOwner,
                selector,
                preview,
                imageCapture
            )
        } catch (e: Exception) {
            e.printStackTrace()
            errorText = "Ошибка запуска камеры: ${e.message}"
        }
    }

    LaunchedEffect(hasCameraPermission) {
        if (hasCameraPermission) {
            startCamera()
        }
    }

    fun captureAndRecognize() {
        if (!hasCameraPermission || isProcessing) return

        isProcessing = true
        errorText = null

        imageCapture.takePicture(
            executor,
            object : ImageCapture.OnImageCapturedCallback() {
                override fun onCaptureSuccess(imageProxy: ImageProxy) {
                    val mediaImage = imageProxy.image
                    if (mediaImage == null) {
                        imageProxy.close()
                        isProcessing = false
                        return
                    }

                    val inputImage = InputImage.fromMediaImage(
                        mediaImage,
                        imageProxy.imageInfo.rotationDegrees
                    )

                    recognizer
                        .process(inputImage)
                        .addOnSuccessListener { result ->
                            val fullText = result.text ?: ""

                            scope.launch {
                                try {
                                    if (config.useRemoteOcr) {
                                        val remote = callRemoteOcrParse(context, config, fullText)
                                        if (remote.ok && remote.data != null) {
                                            val base = remote.data
                                            val lc = detectLocalCarrierName(fullText)
                                            val lt = detectLocalTrackingNo(fullText, lc)
                                            //val tuid = detectTuid(fullText)
                                            val tuid = lt ?: base.trackingNo

                                            //onResult(
                                            //    remote.data.copy(
                                            //        tuid = tuid,
                                            //        localCarrierName = lc,
                                            //        localTrackingNo = lt
                                            //    )
                                            //)
                                            onResult(
                                                base.copy(
                                                    tuid = tuid,
                                                    localCarrierName = lc,
                                                    localTrackingNo = lt
                                                )
                                            )
                                        } else {
                                            // fallback: локальный парсер
                                            val basic = parseOcrText(fullText)
                                            val advanced = buildOcrParcelDataFromText(
                                                fullText = fullText,
                                                trackingNo = basic.trackingNo,
                                                destConfig = destConfig,
                                                nameDict = nameDict
                                            )
                                            val merged = basic.copy(
                                                receiverCountryCode    = advanced.receiverCountryCode    ?: basic.receiverCountryCode,
                                                receiverCompany        = advanced.receiverCompany        ?: basic.receiverCompany,
                                                receiverForwarderCode  = advanced.receiverForwarderCode  ?: basic.receiverForwarderCode,
                                                receiverCellCode       = advanced.receiverCellCode       ?: basic.receiverCellCode,
                                                receiverName           = advanced.receiverName           ?: basic.receiverName
                                            )

                                            val lc = detectLocalCarrierName(fullText)
                                            val lt = detectLocalTrackingNo(fullText, lc)
                                            val tuid = detectTuid(fullText)

                                            onResult(
                                                merged.copy(
                                                    tuid = tuid,
                                                    localCarrierName = lc,
                                                    localTrackingNo = lt
                                                )
                                            )

                                            errorText = remote.errorMessage?.let {
                                                "Удалённый парсер не сработал: $it (использован локальный)"
                                            }
                                        }

                                    } else {
                                        val basic = parseOcrText(fullText)
                                        val advanced = buildOcrParcelDataFromText(
                                            fullText = fullText,
                                            trackingNo = basic.trackingNo,
                                            destConfig = destConfig,
                                            nameDict = nameDict
                                        )
                                        val merged = basic.copy(
                                            receiverCountryCode    = advanced.receiverCountryCode    ?: basic.receiverCountryCode,
                                            receiverCompany        = advanced.receiverCompany        ?: basic.receiverCompany,
                                            receiverForwarderCode  = advanced.receiverForwarderCode  ?: basic.receiverForwarderCode,
                                            receiverCellCode       = advanced.receiverCellCode       ?: basic.receiverCellCode,
                                            receiverName           = advanced.receiverName           ?: basic.receiverName
                                        )
                                        val lc = detectLocalCarrierName(fullText)
                                        val lt = detectLocalTrackingNo(fullText, lc)
                                        val tuid = detectTuid(fullText)

                                        onResult(
                                            merged.copy(
                                                tuid = tuid,
                                                localCarrierName = lc,
                                                localTrackingNo = lt
                                            )
                                        )
                                    }
                                } finally {
                                    isProcessing = false
                                }
                            }
                        }
                        .addOnFailureListener { e ->
                            errorText = "Не удалось распознать: ${e.message}"
                            isProcessing = false
                        }
                        .addOnCompleteListener {
                            imageProxy.close()
                        }
                }

                override fun onError(exception: ImageCaptureException) {
                    errorText = "Ошибка камеры: ${exception.message}"
                    isProcessing = false
                }
            }
        )
    }



    // ===== Привязка VOL_DOWN к captureAndRecognize =====
    DisposableEffect(hasCameraPermission) {
        if (hasCameraPermission) {
            onBindHardwareTrigger { captureAndRecognize() }
        } else {
            onBindHardwareTrigger(null)
        }

        onDispose {
            onBindHardwareTrigger(null)
        }
    }

    // ===== UI =====
    Box(
        modifier = modifier.fillMaxSize()
    ) {
        if (!hasCameraPermission) {
            Column(
                modifier = Modifier
                    .align(Alignment.Center)
                    .padding(16.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text("Нет доступа к камере")
                Spacer(Modifier.height(16.dp))
                OutlinedButton(onClick = onCancel) {
                    Text("Назад")
                }
            }
        } else {
            // Превью камеры
            AndroidView(
                modifier = Modifier.fillMaxSize(),
                factory = { previewView }
            )
            // БАННЕР DEFAULT поверх камеры
            if (isDefaultMode) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .fillMaxHeight(0.15f)
                        .align(Alignment.TopCenter),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "DEFAULT",
                        color = MaterialTheme.colorScheme.error,
                        style = MaterialTheme.typography.headlineLarge,
                        fontWeight = androidx.compose.ui.text.font.FontWeight.Black
                    )
                }
            }

            // Нижняя панель с кнопками
            Column(
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .fillMaxWidth()
                    .padding(16.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                if (errorText != null) {
                    Text(
                        text = errorText!!,
                        color = MaterialTheme.colorScheme.error
                    )
                    Spacer(Modifier.height(8.dp))
                }

                if (isProcessing) {
                    Text("Обработка снимка…")
                    Spacer(Modifier.height(8.dp))
                } else {
                    Text("Нажми громкость вниз или кнопку ниже для скана")
                    Spacer(Modifier.height(8.dp))
                }

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    OutlinedButton(
                        onClick = onCancel,
                        enabled = !isProcessing
                    ) {
                        Text("Отмена")
                    }

                    Button(
                        onClick = { onBarcodeClick?.invoke() },
                        enabled = !isProcessing
                    ) {
                        Text("BarScann")
                    }

                    Button(
                        onClick = { captureAndRecognize() },
                        enabled = !isProcessing
                    ) {
                        Text("OcrScann")
                    }

                    Button(
                        onClick = { onBpClick?.invoke() },
                        enabled = !isProcessing
                    ) {
                        Text("BP")
                    }

                    OutlinedButton(
                        onClick = onCancel,
                        enabled = !isProcessing
                    ) {
                        Text("Отмена")
                    }
                }
            }
        }
    }
}

fun parseDestConfigJson(json: String?): List<DestCountryCfg> {
    val s = json?.trim()
    if (s.isNullOrEmpty() || s.equals("null", true) || s.equals("undefined", true)) {
        return emptyList()
    }

    return try {
        val arr = org.json.JSONArray(s)
        val result = mutableListOf<DestCountryCfg>()

        for (i in 0 until arr.length()) {
            val o = arr.getJSONObject(i)

            val aliases = o.optJSONArray("aliases")?.let { ja ->
                (0 until ja.length()).map { ja.optString(it) }
            } ?: emptyList()

            val fwArr = o.optJSONArray("forwarders")
            val forwarders = mutableListOf<DestForwarder>()
            if (fwArr != null) {
                for (j in 0 until fwArr.length()) {
                    val f = fwArr.getJSONObject(j)
                    val fAliases = f.optJSONArray("aliases")?.let { ja ->
                        (0 until ja.length()).map { ja.optString(it) }
                    } ?: emptyList()

                    forwarders += DestForwarder(
                        code = f.optString("code", ""),
                        name = f.optString("name", ""),
                        aliases = fAliases
                    )
                }
            }

            result += DestCountryCfg(
                id         = o.optInt("id"),
                code_iso2  = o.optString("code_iso2", ""),
                code_iso3  = o.optString("code_iso3", ""),
                name_en    = o.optString("name_en", ""),
                name_local = o.optString("name_local", ""),
                aliases    = aliases,
                forwarders = forwarders
            )
        }

        result
    } catch (e: Exception) {
        e.printStackTrace()
        emptyList()
    }
}


fun detectDestCountryAndForwarder(
    textRaw: String,
    countries: List<DestCountryCfg>
): DetectedDest {
    val text = textRaw.lowercase()

    // 1. Страна: ищем, у кого больше совпавших алиасов
    var bestCountry: DestCountryCfg? = null
    var bestCountryScore = 0

    for (c in countries) {
        var score = 0
        for (alias in c.aliases) {
            val a = alias.lowercase()
            if (a.isNotBlank() && text.contains(a)) {
                score++
            }
        }
        if (score > bestCountryScore) {
            bestCountryScore = score
            bestCountry = c
        }
    }

    // 2. Форвардер: внутри выбранной страны
    var bestForwarder: DestForwarder? = null
    var bestFwScore = 0

    val country = bestCountry
    if (country != null) {
        for (fw in country.forwarders) {
            var score = 0
            for (alias in fw.aliases) {
                val a = alias.lowercase()
                if (a.isNotBlank() && text.contains(a)) {
                    score++
                }
            }
            if (score > bestFwScore) {
                bestFwScore = score
                bestForwarder = fw
            }
        }
    }

    return DetectedDest(
        countryCode   = country?.code_iso2,
        countryName   = country?.name_en,
        forwarderCode = bestForwarder?.code,
        forwarderName = bestForwarder?.name
    )
}

fun buildOcrParcelDataFromText(
    fullText: String,
    trackingNo: String?,
    destConfig: List<DestCountryCfg>,
    nameDict: NameDict?
): OcrParcelData {

    val detected = detectDestCountryAndForwarder(fullText, destConfig)
    val cellCode = detectCellCode(fullText)

    var forwarderCode = detected.forwarderCode
    var forwarderName = detected.forwarderName
    var countryIso2   = detected.countryCode

    // 1) если не нашли по destConfig — пробуем по ячейке
    if (forwarderCode == null && cellCode != null) {
        detectForwarderByCellCode(cellCode)?.let { code ->
            forwarderCode = code
            forwarderName = forwarderCompanyByCode(code)
        }
    }

    // 2) если всё ещё не нашли — пробуем по тексту (COLIBRI/KOLI/…)
    if (forwarderCode == null) {
        detectForwarderByText(fullText)?.let { code ->
            forwarderCode = code
            forwarderName = forwarderCompanyByCode(code)
        }
    }

    // 3) страна: форвардер важнее “DE/Germany/Hessen” в тексте
    if (forwarderCode != null) {
        // форвардеры, которые всегда AZ
        if (forwarderCode in setOf("COLIBRI","KOLLI","ASER","CAMEX","KARGOFLEX","POSTLINK")) {
            countryIso2 = "AZ"
        } else if (forwarderCode == "CAMARATC") {
            // CAMARATC: GE/KG по адресу, если получилось распознать
            defaultCountryIso2ByForwarder("CAMARATC", fullText)?.let { countryIso2 = it }
            // если не получилось — лучше оставить null, чем DE
            if (countryIso2 == "DE") countryIso2 = null
        } else {
            // общий fallback
            if (countryIso2 == null || countryIso2 == "DE") {
                defaultCountryIso2ByForwarder(forwarderCode!!, fullText)?.let { countryIso2 = it }
            }
        }
    }

    val clientName = detectClientName(
        text = fullText,
        forwarderCode = forwarderCode,
        cellCode = cellCode,
        nameDict = nameDict
    )

    return OcrParcelData(
        trackingNo            = trackingNo,
        receiverCountryCode   = countryIso2,
        receiverCompany       = forwarderName,
        receiverForwarderCode = forwarderCode,
        receiverCellCode      = cellCode,
        receiverName          = clientName,
        receiverAddress       = null,
        weightKg              = null,
        sizeL                 = null,
        sizeW                 = null,
        sizeH                 = null
    )
}
fun sanitizeCellCode(raw: String): String {
    val upper = raw.uppercase()

    // 1) Частый OCR-ошибочный вариант Postlink: PL"O"xxxx -> PL0xxxx
    Regex("^([A-Z]{2})O(\\d{3,8})$").matchEntire(upper)?.let { m ->
        return m.groupValues[1] + "0" + m.groupValues[2]
    }

    // 2) Основной случай: буквы + цифры (с возможными O в цифровой части)
    Regex("^([A-Z]{1,3})([0-9O]{3,8})$").matchEntire(upper)?.let { m ->
        val prefix = m.groupValues[1]
        val numeric = m.groupValues[2].replace('O', '0')
        return prefix + numeric
    }

    // 3) Без распознавания шаблона просто возвращаем верхний регистр
    return upper
}

/**
 * Пытаемся вытащить код ячейки вида A66050 / AS228905 / C163361.
 * Пока максимально простой вариант: первая подходящая комбинация буквы+цифры.
 */
fun detectCellCode(text: String): String? {
    // Ищем что-то вроде A12345, AS228905, C163361
    val regex = Regex("\\b[A-Z]{1,3}\\d{3,8}\\b")
    return regex.find(text.replace("\n", " "))?.value?.let(::sanitizeCellCode)}

/**
 * Пытаемся вытащить ФИО конечного клиента.
 * Черновой вариант: первая строка без цифр, в которой >=2 слов с заглавной буквы.
 */
fun looksLikePersonName(lineRaw: String, dict: NameDict?): Boolean {
    val line = lineRaw.trim()
    if (line.isEmpty()) return false
    if (line.length > 45) return false

    val low = line.lowercase()

    // словарь: точные совпадения
    if (dict != null) {
        if (dict.exactBad.contains(low)) return false
        for (sub in dict.substrBad) {
            if (sub.isNotEmpty() && low.contains(sub)) {
                return false
            }
        }
    }

    // хотя бы одна буква
    if (!Regex("[A-Za-zÄÖÜäöüß]").containsMatchIn(line)) return false
    // не хотим цифр
    if (Regex("\\d").containsMatchIn(line)) return false

    val words = line.split(Regex("\\s+")).filter { it.isNotBlank() }
    if (words.size < 2) return false

    val capitalWords = words.count { w ->
        val c = w.firstOrNull()
        c != null && c.isLetter() && c.isUpperCase()
    }
    if (capitalWords == 0) return false

    return true
}

fun cleanNameLine(
    lineRaw: String,
    forwarderCode: String?,
    cellCode: String?
): String? {
    var line = lineRaw.trim()
    if (line.isEmpty()) return null

    // служебные префиксы вначале
    line = Regex(
        pattern = "^(to|an|von|from|empf[aäå]nger(?:in)?|addressee|receiver|contact|billing\\s+no)\\s*:?\\s*",
        options = setOf(RegexOption.IGNORE_CASE)
    ).replace(line, "")
// либо вообще без options, если регистр не критичен

    // спец-слова по форвардеру
    val patterns = mutableListOf<Regex>()
    // общие форвардер-бренды (работают даже когда forwarderCode == null)
    patterns += Regex("\\bCOLIBR\\p{L}*(?:\\s+EXP\\p{L}*)?\\b", RegexOption.IGNORE_CASE)
    patterns += Regex("\\bKOLI\\p{L}*(?:\\s*EXP\\p{L}*)?\\b", RegexOption.IGNORE_CASE)
    patterns += Regex("\\b(CAMEX|ASER|POSTLINK|KARGOFLEX|CAMARATC)\\p{L}*\\b", RegexOption.IGNORE_CASE)

    when (forwarderCode) {
        "COLIBRI" -> {
            patterns += Regex("\\bCOLIBR\\p{L}*\\s+EXP\\p{L}*\\b", RegexOption.IGNORE_CASE)
            patterns += Regex("\\bCOLIBR\\p{L}*\\b", RegexOption.IGNORE_CASE)
        }
        "KOLLI" -> {
            patterns += Regex("\\bKOLI\\p{L}*\\s*EXP\\p{L}*\\b", RegexOption.IGNORE_CASE)
            patterns += Regex("\\bKOLI\\p{L}*\\b", RegexOption.IGNORE_CASE)
        }
        "ASER" -> {
            patterns += Regex("\\bASER\\p{L}*\\b", RegexOption.IGNORE_CASE)
        }
        "CAMEX" -> {
            patterns += Regex("\\bCAMEX\\p{L}*\\b", RegexOption.IGNORE_CASE)
        }
        "KARGOFLEX" -> {
            patterns += Regex("\\bKARGO?FLEX\\p{L}*\\b", RegexOption.IGNORE_CASE)
        }
        "CAMARATC" -> {
            patterns += Regex("\\bCAMARATC\\p{L}*\\b", RegexOption.IGNORE_CASE)
        }
        "POSTLINK" -> {
            patterns += Regex("\\bPOSTLINK\\p{L}*\\b", RegexOption.IGNORE_CASE)
        }
    }

    // общие бренды
    patterns += Regex("\\bTLS\\s+CARGO\\b", RegexOption.IGNORE_CASE)
    patterns += Regex("\\bE\\p{L}{0,2}PRESS\\p{L}*\\b", RegexOption.IGNORE_CASE)
    patterns += Regex("\\bEXP\\b", RegexOption.IGNORE_CASE)
    patterns += Regex("\\bCARGO\\b", RegexOption.IGNORE_CASE)
    patterns += Regex("\\bSHIP\\b", RegexOption.IGNORE_CASE)

    patterns.forEach { re -> line = re.replace(line, " ") }

    // убрать код ячейки
    if (!cellCode.isNullOrBlank()) {
        val reCell = Regex("\\b${Regex.escape(cellCode)}\\b", RegexOption.IGNORE_CASE)
        line = reCell.replace(line, " ")
    }

    // выкинуть ведущий "код" с цифрами
    line = Regex("^\\s*\\S*\\d+\\S*\\s+", RegexOption.IGNORE_CASE).replace(line, " ")

    // хвосты-организации
    line = Regex(
        "\\b(exp|cargo|llc|gmbh|gimbh|shop|online|spa|hub)\\b\\.?$",
        RegexOption.IGNORE_CASE
    ).replace(line, "")

    // нормализация пробелов/дефисов
    line = Regex("\\s*[-–—]+\\s*").replace(line, " ")
    line = line.trim(' ', '\t', '-', ',', ':', '.', ';', '/')
    line = Regex("\\s{2,}").replace(line, " ")

    if (line.isBlank()) return null

    // если одно и то же имя повторено через разделители – оставляем один раз
    val parts = Regex("\\s*[-–—,:;/]+\\s*")
        .split(line)
        .map { it.trim() }
        .filter { it.isNotEmpty() }

    if (parts.size > 1) {
        val lowerUnique = parts.map { it.lowercase() }.toSet()
        if (lowerUnique.size == 1) {
            line = parts.first()
        }
    }

    return if (line.isBlank()) null else line
}
fun detectClientName(
    text: String,
    forwarderCode: String?,
    cellCode: String?,
    nameDict: NameDict?
): String? {
    val lines = text.lines()
        .map { it.trim() }
        .filter { it.isNotEmpty() }

    if (lines.isEmpty()) return null

    // 1) попробуем сначала строки рядом с форвардером
    val forwarderAliases: Map<String, List<String>> = mapOf(
        "COLIBRI"   to listOf("colibri express", "colibriexpress", "colibri exp", "colibri"),
        "KOLLI"     to listOf("koliexpress", "koli express", "koli-express", "koliexp", "koli"),
        "ASER"      to listOf("aser express", "aser exp", "aser"),
        "CAMEX"     to listOf("camex", "camex llc", "camex express"),
        "KARGOFLEX" to listOf("kargoflex"),
        "CAMARATC"  to listOf("camaratc"),
        "POSTLINK"  to listOf("postlink")
    )

    if (forwarderCode != null && forwarderAliases.containsKey(forwarderCode)) {
        val aliases = forwarderAliases[forwarderCode]!!
        val lowerLines = lines.map { it.lowercase() }

        for (i in lowerLines.indices) {
            val l = lowerLines[i]
            if (aliases.any { it in l }) {
                // проверяем текущую строку и следующую
                val idxs = listOf(i, i + 1).filter { it in lines.indices }
                for (idx in idxs) {
                    val cleaned = cleanNameLine(lines[idx], forwarderCode, cellCode)
                    if (cleaned != null && looksLikePersonName(cleaned, nameDict)) {
                        return cleaned
                    }
                }
            }
        }
    }

    // 2) fallback – любая строка, похожая на имя
    for (line in lines) {
        val cleaned = cleanNameLine(line, forwarderCode, cellCode)
        if (cleaned != null && looksLikePersonName(cleaned, nameDict)) {
            return cleaned
        }
    }

    return null
}

fun detectLocalCarrierName(textRaw: String): String? {
    val t = textRaw.lowercase()

    return when {
        Regex("\\bhermes\\b").containsMatchIn(t) -> "HERMES"
        Regex("\\bgls\\b").containsMatchIn(t) -> "GLS"
        Regex("\\bups\\b").containsMatchIn(t) || Regex("\\b1z[0-9a-z]{16}\\b").containsMatchIn(t) -> "UPS"
        Regex("\\bamazon\\b").containsMatchIn(t) || Regex("\\btba\\d{10,}\\b").containsMatchIn(t) -> "AMAZON"
        Regex("\\bdhl\\b").containsMatchIn(t) || t.contains("deutsche post") -> "DHL"
        else -> null
    }
}

fun detectLocalTrackingNo(textRaw: String, carrier: String?): String? {
    val text = textRaw.replace("\n", " ").replace("\r", " ").uppercase()

    // UPS
    Regex("\\b1Z[0-9A-Z]{16}\\b").find(text)?.let { return it.value }

    // Amazon
    Regex("\\bTBA\\d{10,}\\b").find(text)?.let { return it.value }

    // ID No / IDNO
    Regex("\\bID\\s*NO\\b\\s*[:#\\-]?\\s*(\\d{8,20})\\b").find(text)?.let {
        return it.groupValues[1]
    }

    // digits candidates 8..20, но режем телефоны
    val matches = Regex("\\b\\d{8,20}\\b").findAll(text).toList()
    if (matches.isEmpty()) return null

    fun isPhoneLike(m: MatchResult): Boolean {
        val start = (m.range.first - 20).coerceAtLeast(0)
        val ctx = text.substring(start, m.range.first)
        return ctx.contains("PHONE") || ctx.contains("TEL") || ctx.contains("CONTACT")
    }

    val candidates = matches
        .filterNot { isPhoneLike(it) }
        .map { it.value }

    if (candidates.isEmpty()) return null

    fun score(v: String): Int {
        var s = v.length

        // типовые подсказки по длине
        if (carrier == "GLS" && v.length == 11) s += 50
        if (carrier == "HERMES" && v.length in 14..16) s += 50
        if (carrier == "DHL" && v.length >= 12) s += 20

        return s
    }

    return candidates.maxByOrNull { score(it) }
}



fun normalizeCountryForSelect(raw: String?): String? {
    val c = raw?.trim()?.takeIf { it.isNotBlank() } ?: return null
    return when (c.uppercase()) {
        "AZB", "AZE", "AZE" -> "AZ"
        "TBS", "GEO"        -> "GE"
        "DEU", "DE"         -> "DE"
        "KGZ", "KG"         -> "KG"
        else                -> c.uppercase()
    }
}

fun detectTuid(textRaw: String): String? {
    val lines = textRaw.lines().map { it.trim() }.filter { it.isNotEmpty() }

    val re = Regex(
        "(?i)\\b(colibri\\s*express|colibriexpress|koli\\s*express|koliexpress|camex|aser\\s*express|postlink|kargoflex|camaratc)\\b\\s*[-–—:]?\\s*([A-Z]{1,3}\\d{3,8})\\b"
    )

    for (line in lines) {
        val m = re.find(line) ?: continue
        val fw = m.groupValues[1].uppercase().replace(Regex("\\s+"), " ").trim()
        val cell = m.groupValues[2].uppercase()
        return "$fw -$cell"
    }
    return null
}
fun detectForwarderByText(textRaw: String): String? {
    val t = textRaw.lowercase()

    return when {
        Regex("\\bcolibri\\b").containsMatchIn(t) -> "COLIBRI"
        Regex("\\b(koli|koliexp|koliexpress|koli\\s*express)\\b").containsMatchIn(t) -> "KOLLI"
        Regex("\\bcamex\\b").containsMatchIn(t) -> "CAMEX"
        Regex("\\baser\\b").containsMatchIn(t) -> "ASER"
        Regex("\\bpostlink\\b").containsMatchIn(t) -> "POSTLINK"
        Regex("\\bkargoflex\\b").containsMatchIn(t) -> "KARGOFLEX"
        Regex("\\bcamaratc\\b").containsMatchIn(t) -> "CAMARATC"
        else -> null
    }
}


data class ScanAction(
    val action: String,
    val fieldId: String? = null,
    val fieldName: String? = null,
    val endpoint: String? = null
)

data class ScanTaskConfig(
    val taskId: String,
    val defaultMode: String,   // "ocr" | "barcode" | "qr"
    val modes: Set<String>,
    val barcodeAction: ScanAction? = null,
    val qrAction: ScanAction? = null,
)


fun parseScanAction(obj: JSONObject?): ScanAction? {
    if (obj == null) return null
    val action = obj.optString("action", "").takeIf { it.isNotBlank() } ?: return null
    val fieldId = obj.optString("field_id", "").takeIf { it.isNotBlank() }
    val fieldName = obj.optString("field_name", "").takeIf { it.isNotBlank() }
    val endpoint = obj.optString("endpoint", "").takeIf { it.isNotBlank() }
    return ScanAction(action = action, fieldId = fieldId, fieldName = fieldName, endpoint = endpoint)
}

fun parseScanTaskConfig(json: String): ScanTaskConfig? = try {
    val obj = JSONObject(json)
    val taskId = obj.optString("task_id", "unknown")
    val def = obj.optString("default_mode", "ocr")
    val arr = obj.optJSONArray("modes")
    val modes = mutableSetOf<String>()
    if (arr != null) for (i in 0 until arr.length()) modes += arr.optString(i)
    val barcode = parseScanAction(obj.optJSONObject("barcode"))
    val qr = parseScanAction(obj.optJSONObject("qr"))
    ScanTaskConfig(taskId, def, modes, barcode, qr)
} catch (e: Exception) { null }