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

 // колбэк, который будет вызываться при VOL_DOWN, когда открыт OCR
    var ocrHardwareTrigger by remember { mutableStateOf<(() -> Unit)?>(null) }
    LaunchedEffect(showWebView, showOcr, ocrHardwareTrigger, webViewRef) {
        when {
            // Если открыт экран OCR и есть триггер — VOL_DOWN стреляет OCR
            showOcr && ocrHardwareTrigger != null -> {
                MainActivity.onVolumeDown = {
                    ocrHardwareTrigger?.invoke()
                }
                MainActivity.onVolumeUp = null
            }

            // Если открыт WebView (форма склада)
            showWebView -> {
                MainActivity.onVolumeDown = {
                    // перед новым сканом — если форма заполнена, добавить посылку и очистить
                    webViewRef?.let { web ->
                        prepareFormForNextScanInWebView(web)
                    }
                    // открыть OCR
                    showOcr = true
                }
                MainActivity.onVolumeUp = {
                    // VOL_UP просто чистит форму
                    webViewRef?.let { web ->
                        clearParcelFormInWebView(web)
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
                                    val cookieStr = "PHPSESSID=${result.sessionId}; Path=/; Secure"
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
                        onWebViewReady = { webView ->
                            // сохраняем ссылку, чтобы потом заполнять форму
                            webViewRef = webView

                            // грузим destcountry-конфиг
                            webView.evaluateJavascript(
                                "(function(){var el=document.getElementById('ocr-templates-destcountry');return el?el.textContent:'';})()"
                            ) { json ->
                                try {
                                    val cleaned = json
                                        ?.trim()
                                        ?.removePrefix("\"")
                                        ?.removeSuffix("\"")
                                        ?.replace("\\\"", "\"")
                                        ?: ""

                                    if (cleaned.isNotBlank()) {
                                        val list = parseDestConfigJson(cleaned)
                                        destConfig = list
                                    }
                                } catch (e: Exception) {
                                    e.printStackTrace()
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

            // ОВЕРЛЕЙ OCR ПОВЕРХ WebView (и любого экрана)
            if (showOcr) {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background.copy(alpha = 0.98f)
                ) {
                    OcrScanScreen(
                        modifier = Modifier.fillMaxSize(),
                        destConfig = destConfig,
                        config = config,
                        nameDict = nameDict,
                        onResult = { ocrData ->
                            showOcr = false
                            webViewRef?.let { web ->
                                fillParcelFormInWebView(web, ocrData)
                            }
                        },
                        onCancel = {
                            showOcr = false
                        },
                        onBindHardwareTrigger = { action ->
                            ocrHardwareTrigger = action
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

fun parseOcrTemplates(json: String): OcrTemplates? {
    val root = JSONObject(json)

    val version = root.optInt("version", 1)
    val carriersObj = root.optJSONObject("carriers") ?: return null

    val carriersMap = mutableMapOf<String, CarrierTemplate>()

    val carrierNames = carriersObj.keys()
    while (carrierNames.hasNext()) {
        val code = carrierNames.next()
        val cObj = carriersObj.optJSONObject(code) ?: continue

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
            val lines   = if (rObj.has("lines"))   rObj.optInt("lines")   else null

            val mvArr = rObj.optJSONArray("marker_variants")
            val markerVariants = mutableListOf<String>()
            if (mvArr != null) {
                for (i in 0 until mvArr.length()) {
                    markerVariants += mvArr.optString(i)
                }
            }

            rulesMap[rk] = RuleConfig(
                type = type,
                pattern = pattern,
                markerVariants = if (markerVariants.isEmpty()) null else markerVariants,
                lines = lines
            )
        }

        carriersMap[code] = CarrierTemplate(
            displayName = displayName,
            rules = rulesMap
        )
    }

    return OcrTemplates(
        version = version,
        carriers = carriersMap
    )
}
@Composable
fun DeviceWebViewScreen(
    config: DeviceConfig,
    onWebViewReady: (WebView) -> Unit,
    onSessionEnded: () -> Unit,
    modifier: Modifier = Modifier,
    onTemplatesLoaded: (OcrTemplates?) -> Unit,
    onNameDictLoaded: (NameDict?) -> Unit    // <<< НОВЫЙ колбэк
) {
    AndroidView(
        modifier = modifier.fillMaxSize(),
        factory = { ctx ->
            WebView(ctx).apply {
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
                        }

                        val base = normalizeServerUrl(config.serverUrl)

                        if (!url.isNullOrBlank()
                            && url.startsWith(base)
                            && url.contains("/login")
                        ) {
                            onSessionEnded()
                        }

                        // 1) шаблоны перевозчиков
                        view?.evaluateJavascript(
                            """
        (function(){
          var el = document.getElementById('ocr-templates');
          if (!el) return null;
          return el.textContent || el.innerText || null;
        })();
        """.trimIndent()
                        ) { raw ->
                            try {
                                if (raw == null || raw == "null") {
                                    onTemplatesLoaded(null)
                                } else {
                                    val unquoted = raw
                                        .removePrefix("\"")
                                        .removeSuffix("\"")
                                        .replace("\\\"", "\"")
                                        .replace("\\n", "\n")
                                        .replace("\\t", "\t")

                                    val tmpl = parseOcrTemplates(unquoted)
                                    onTemplatesLoaded(tmpl)
                                }
                            } catch (e: Exception) {
                                e.printStackTrace()
                                onTemplatesLoaded(null)
                            }
                        }

                        // 2) словарь для имён
                        view?.evaluateJavascript(
                            """
        (function(){
          var el = document.getElementById('ocr-dicts');
          if (!el) return null;
          return el.textContent || el.innerText || null;
        })();
        """.trimIndent()
                        ) { raw ->
                            try {
                                if (raw == null || raw == "null") {
                                    onNameDictLoaded(null)
                                } else {
                                    val unquoted = raw
                                        .removePrefix("\"")
                                        .removeSuffix("\"")
                                        .replace("\\\"", "\"")
                                        .replace("\\n", "\n")
                                        .replace("\\t", "\t")

                                    val dict = parseNameDictJson(unquoted)
                                    onNameDictLoaded(dict)
                                }
                            } catch (e: Exception) {
                                e.printStackTrace()
                                onNameDictLoaded(null)
                            }
                        }
                        // НОВОЕ: словарь "ocr-dicts"
                        view?.evaluateJavascript(
                            """
    (function(){
      var el = document.getElementById('ocr-dicts');
      if (!el) return null;
      return el.textContent || el.innerText || null;
    })();
    """.trimIndent()
                        ) { raw ->
                            try {
                                if (raw == null || raw == "null") {
                                    onNameDictLoaded(null)
                                } else {
                                    val unquoted = raw
                                        .removePrefix("\"")
                                        .removeSuffix("\"")
                                        .replace("\\\"", "\"")
                                        .replace("\\n", "\n")
                                        .replace("\\t", "\t")

                                    val dict = parseNameDictJson(unquoted)
                                    onNameDictLoaded(dict)
                                }
                            } catch (e: Exception) {
                                e.printStackTrace()
                                onNameDictLoaded(null)
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
    val sizeH: Double? = null
)
fun fillParcelFormInWebView(
    webView: WebView,
    data: OcrParcelData
) {
    // для логов — чтобы понять, что реально прилетает
    println("### fillParcelFormInWebView() data = $data")

    fun esc(str: String): String =
        str.replace("\\", "\\\\")
            .replace("'", "\\'")
            .replace("\n", " ")
            .replace("\r", " ")

    // ===== НОРМАЛИЗУЕМ ВСЁ, ЧТО ПРИШЛО =====

    var tracking = data.trackingNo?.trim().takeUnless { it.isNullOrEmpty() }

    // код страны, как пришёл от сервера (AZB, DE, KGZ и т.п.)
    var rawCountry = data.receiverCountryCode?.trim().takeUnless { it.isNullOrEmpty() }

    // имя как есть
    var rawName = data.receiverName?.trim().takeUnless { it.isNullOrEmpty() }

    // код форвардера и название
    var forwarderCode = data.receiverForwarderCode?.trim().takeUnless { it.isNullOrEmpty() }
    var forwarderName = data.receiverCompany?.trim().takeUnless { it.isNullOrEmpty() }

    // код ячейки
    val cellCode = data.receiverCellCode?.trim().takeUnless { it.isNullOrEmpty() }

    // ===== если код форвардера не пришёл, но имя выглядит как "KOLLI AYXAN MUSAYEV" – режем =====
    if (forwarderCode.isNullOrBlank() && !rawName.isNullOrBlank()) {
        val m = Regex("^([A-Z]{3,8})\\s+(.+)$").find(rawName!!)
        if (m != null) {
            val candidate = m.groupValues[1]
            val rest      = m.groupValues[2]
            // простой хак: считаем это кодом
            forwarderCode = candidate
            rawName = rest.trim()
            println("### parsed forwarderCode from name: $forwarderCode, name: $rawName")
        }
    }

    // если код форвардера всё-таки есть — на всякий случай ещё раз уберём его из имени в начале
    if (!forwarderCode.isNullOrBlank() && !rawName.isNullOrBlank()) {
        val re = Regex("^\\s*${Regex.escape(forwarderCode!!)}\\s+", RegexOption.IGNORE_CASE)
        val cleaned = re.replace(rawName!!, "").trim()
        if (cleaned.isNotEmpty() && cleaned != rawName) {
            println("### cleaned name by forwarderCode: '$rawName' -> '$cleaned'")
            rawName = cleaned
        }
    }

    // ===== код для select =====
    val countryForSelect: String? = rawCountry?.let { c ->
        when (c.uppercase()) {
            "AZB", "AZE" -> "AZ"   // Azerbaijan
            "DEU"        -> "DE"   // Germany
            "GEO"        -> "GE"   // Georgia
            "KGZ"        -> "KG"   // Kyrgyzstan
            else         -> c.uppercase()
        }
    }

    // ===== что писать в "Компания получателя" =====
    // в приоритете красивое имя компании, если его нет — код форвардера (KOLLI)
    val companyFieldValue: String? = when {
        !forwarderName.isNullOrBlank() -> forwarderName
        !forwarderCode.isNullOrBlank() -> forwarderCode
        else -> null
    }

    // подпись рядом с TUID (маленький серый текст)
    val carrierInfo = buildString {
        if (!forwarderCode.isNullOrBlank()) append(forwarderCode)
        if (!forwarderName.isNullOrBlank()) {
            if (isNotEmpty()) append(" / ")
            append(forwarderName)
        }
        if (!countryForSelect.isNullOrBlank()) {
            if (isNotEmpty()) append(" / ")
            append(countryForSelect)
        }
    }

    // ===== СКРИПТ ДЛЯ WEBVIEW =====

    val js = buildString {
        append("(function(){")
        append(
            """
            function setVal(id,v){
              var e=document.getElementById(id);
              if(e){
                e.value=v;
                e.dispatchEvent(new Event('input',{bubbles:true}));
              }
            }
            function setSelectVal(id,v){
              var e=document.getElementById(id);
              if(e){
                e.value = v;
                e.dispatchEvent(new Event('change',{bubbles:true}));
              }
            }
            function setText(id,text){
              var e=document.getElementById(id);
              if(e){
                e.textContent = text;
              }
            }
            """.trimIndent()
        )

        // TUID + трек
        tracking?.let {
            val v = esc(it)
            append("setVal('tuid','$v');")
            append("setVal('trackingNo','$v');")
        }

        // страна
        countryForSelect?.let {
            append("setSelectVal('receiverCountry','${esc(it)}');")
        }

        // перевозчик: код форвардера -> carrierName + hidden carrierCode
        forwarderCode?.let {
            val v = esc(it)
            append("setVal('carrierName','$v');")
            append("setVal('carrierCode','$v');")
        }

        // компания получателя (поле в форме)
        companyFieldValue?.let {
            append("setVal('receiverCompany','${esc(it)}');")
        }

        // подпись рядом с TUID
        if (carrierInfo.isNotBlank()) {
            append("setText('ocrCarrierInfo',' (${esc(carrierInfo)})');")
        } else {
            append("setText('ocrCarrierInfo','');")
        }

        // ФИО получателя
        rawName?.let {
            append("setVal('receiverName','${esc(it)}');")
        }

        // адрес -> номер ячейки
        cellCode?.let {
            append("setVal('receiverAddress','${esc(it)}');")
        }

        // вес/габариты — заполняем ТОЛЬКО если не null, без нулей по умолчанию
        data.weightKg?.let {
            append("setVal('weightKg','${it}');")
        }
        data.sizeL?.let {
            append("setVal('sizeL','${it}');")
        }
        data.sizeW?.let {
            append("setVal('sizeW','${it}');")
        }
        data.sizeH?.let {
            append("setVal('sizeH','${it}');")
        }

        append("})();")
    }

    webView.post {
        webView.evaluateJavascript(js, null)
    }
}



fun parseOcrText(text: String): OcrParcelData {
    // Разбиваем на строки
    val allLines = text.lines()
    val lines = allLines
        .map { it.trim() }
        .filter { it.isNotEmpty() }

    // ===== ТРЕК =====
    // Длинная строка из латиницы/цифр/дефисов
    val trackingCandidate = lines.firstOrNull {
        it.matches(Regex("[A-Z0-9\\-]{8,}"))
    } ?: lines.firstOrNull()

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
          function setVal(id,v){
            var e=document.getElementById(id);
            if(e){
              e.value=v;
              e.dispatchEvent(new Event('input',{bubbles:true}));
            }
          }
          function setText(id,text){
            var e=document.getElementById(id);
            if(e){
              e.textContent = text;
            }
          }

          setVal('tuid','');
          setVal('trackingNo','');

          setVal('carrierName','');
          setVal('carrierCode','');

          setVal('receiverCountry','');
          setVal('receiverName','');
          setVal('receiverAddress','');
          setVal('receiverCompany','');
          setVal('senderName','');

          setVal('weightKg','');
          setVal('sizeL','');
          setVal('sizeW','');
          setVal('sizeH','');

          setText('ocrCarrierInfo','');
        })();
    """.trimIndent()

    webView.post {
        webView.evaluateJavascript(js, null)
    }
}


fun prepareFormForNextScanInWebView(webView: WebView) {
    val js = """
        (function(){
          function getVal(id){
            var e=document.getElementById(id);
            return e ? e.value.trim() : '';
          }
          function setVal(id,v){
            var e=document.getElementById(id);
            if(e){
              e.value=v;
              e.dispatchEvent(new Event('input',{bubbles:true}));
            }
          }
          function setText(id,text){
            var e=document.getElementById(id);
            if(e){
              e.textContent = text;
            }
          }

          var tuid  = getVal('tuid');
          var track = getVal('trackingNo');

          if (tuid || track) {
            var btn = document.querySelector('button.js-core-link[data-core-action="add_new_item_in"]');
            if (btn) {
              btn.click();
            }

            setVal('tuid','');
            setVal('trackingNo','');

            setVal('carrierName','');
            setVal('carrierCode','');

            setVal('receiverCountry','');
            setVal('receiverName','');
            setVal('receiverAddress','');
            setVal('receiverCompany','');
            setVal('senderName','');

            setVal('weightKg','');
            setVal('sizeL','');
            setVal('sizeW','');
            setVal('sizeH','');

            setText('ocrCarrierInfo','');
          }
        })();
    """.trimIndent()

    webView.post {
        webView.evaluateJavascript(js, null)
    }
}


@Composable
fun OcrScanScreen(
    modifier: Modifier = Modifier,
    destConfig: List<DestCountryCfg>,          // ДОБАВЛЕНО
    config: DeviceConfig,
    nameDict: NameDict?,                         // <<< НОВОЕ
    onResult: (OcrParcelData) -> Unit,
    onCancel: () -> Unit,
    onBindHardwareTrigger: ((() -> Unit)?) -> Unit
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
                                            onResult(remote.data)
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
                                            onResult(merged)
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
                                        onResult(merged)
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
                    horizontalArrangement = Arrangement.SpaceEvenly
                ) {
                    OutlinedButton(
                        onClick = onCancel,
                        enabled = !isProcessing
                    ) {
                        Text("Отмена")
                    }

                    Button(
                        onClick = { captureAndRecognize() },
                        enabled = !isProcessing
                    ) {
                        Text("Сканировать")
                    }
                }
            }
        }
    }
}

fun parseDestConfigJson(json: String): List<DestCountryCfg> {
    val arr = org.json.JSONArray(json)
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

    return result
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

    val detected  = detectDestCountryAndForwarder(fullText, destConfig)
    val cellCode  = detectCellCode(fullText)
    val clientName = detectClientName(
            text = fullText,
    forwarderCode = detected.forwarderCode,
    cellCode = cellCode,
    nameDict = nameDict
    )

    return OcrParcelData(
        trackingNo            = trackingNo,
        receiverCountryCode   = detected.countryCode,
        receiverCompany       = detected.forwarderName,
        receiverForwarderCode = detected.forwarderCode,
        receiverCellCode      = cellCode,
        receiverName          = clientName,
        receiverAddress       = null,
        weightKg              = 0.0,
        sizeL                 = 0.0,
        sizeW                 = 0.0,
        sizeH                 = 0.0
    )
}

/**
 * Пытаемся вытащить код ячейки вида A66050 / AS228905 / C163361.
 * Пока максимально простой вариант: первая подходящая комбинация буквы+цифры.
 */
fun detectCellCode(text: String): String? {
    // Ищем что-то вроде A12345, AS228905, C163361
    val regex = Regex("\\b[A-Z]{1,3}\\d{3,8}\\b")
    return regex.find(text.replace("\n", " "))?.value
}

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