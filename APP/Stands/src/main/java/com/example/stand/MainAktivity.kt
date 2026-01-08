package com.example.stand

import android.content.Context
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.SnackbarHost
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.compose.material3.SnackbarHostState
import com.hoho.android.usbserial.driver.UsbSerialPort
import com.hoho.android.usbserial.driver.UsbSerialProber
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.InputStream
import java.net.HttpURLConnection
import java.net.URL
import java.nio.charset.Charset
import java.security.SecureRandom
import java.security.cert.X509Certificate
import javax.net.ssl.HostnameVerifier
import javax.net.ssl.HttpsURLConnection
import javax.net.ssl.SSLContext
import javax.net.ssl.SSLSession
import javax.net.ssl.TrustManager
import javax.net.ssl.X509TrustManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Intent
import android.content.IntentFilter
import android.hardware.usb.UsbDevice
import android.hardware.usb.UsbManager
import kotlin.math.abs
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import android.graphics.Bitmap
import com.google.zxing.BarcodeFormat
import com.google.zxing.qrcode.QRCodeWriter
import androidx.compose.foundation.Image
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.layout.ContentScale


class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        // если приложение открыли по USB attach — можно сразу запустить логику запроса permission
        if (intent?.action == UsbManager.ACTION_USB_DEVICE_ATTACHED) {
            // опционально: показать тост/лог, или сразу дернуть bind/permission
        }
        setContent { StandApp() }
    }
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
    }
}

@Composable
fun StandApp() {
    val context = LocalContext.current
    val repo = remember { DeviceConfigRepository(context) }
    var config by remember { mutableStateOf(repo.load()) }
    var usbUiState by remember { mutableStateOf(UsbUiState()) }
    var measurementState by remember { mutableStateOf(MeasurementState()) }
    var lastSentMeasurementKey by remember { mutableStateOf<String?>(null) }
    var lastDebugSentAt by remember { mutableStateOf(0L) }
    var displayMode by remember { mutableStateOf(UsbDisplayMode.PROD) }

    var showSettings by remember {
        mutableStateOf(
            config.serverUrl.isBlank() || config.deviceToken.isNullOrBlank() || !config.enrolled
        )
    }

    val snackbarHostState = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()

    MaterialTheme {
        UsbSerialBinder(
            enabled = true,
            onRawLine = { line ->
                val updated = (listOf(line) + usbUiState.logLines).take(400)
                usbUiState = usbUiState.copy(rawJson = line, logLines = updated)
            },
            onJson = { obj ->
                val hCm = obj.optDouble("h_cm", Double.NaN)
                val wCm = obj.optDouble("w_cm", Double.NaN)
                val dCm = obj.optDouble("d_cm", Double.NaN)
                val weightKg = obj.optDouble("weight_kg", Double.NaN)

                val sonarArr = obj.optJSONArray("sonar_cur_mm")
                val s0 = sonarArr?.optInt(0)

                val raw = UsbRawMetrics(
                    scannerToWallMm = s0,
                    weightGrams = if (!weightKg.isNaN()) (weightKg * 1000.0).toInt() else null,
                    widthMm = if (!wCm.isNaN()) (wCm * 10.0) else null,
                    depthMm = if (!dCm.isNaN()) (dCm * 10.0) else null,
                    heightMm = if (!hCm.isNaN()) (hCm * 10.0) else null,
                    scaleZeroOffsetG = obj.optInt("scale_zero_offset_g").takeIf { obj.has("scale_zero_offset_g") },
                    scaleFactor = obj.optDouble("scale_factor", Double.NaN).takeIf { obj.has("scale_factor") && !it.isNaN() }
                )

                val prev = usbUiState.calibrated
                val merged = prev.copy(
                    widthMm = mergeWithTolerance(prev.widthMm, raw.widthMm, SIZE_TOLERANCE_MM),
                    depthMm = mergeWithTolerance(prev.depthMm, raw.depthMm, SIZE_TOLERANCE_MM),
                    heightMm = mergeWithTolerance(prev.heightMm, raw.heightMm, SIZE_TOLERANCE_MM),
                    weightGrams = mergeIntWithTolerance(prev.weightGrams, raw.weightGrams, 30),
                    scannerToWallMm = raw.scannerToWallMm ?: prev.scannerToWallMm,
                    scaleCalibration = ScaleCalibration(
                        zeroOffsetG = raw.scaleZeroOffsetG ?: prev.scaleCalibration.zeroOffsetG,
                        factor = raw.scaleFactor ?: prev.scaleCalibration.factor
                    )
                )

                usbUiState = usbUiState.copy(rawMetrics = raw, calibrated = merged)
            },
            onStatus = { st ->
                val s = "[USB] $st"
                val updated = (listOf(s) + usbUiState.logLines).take(400)
                usbUiState = usbUiState.copy(logLines = updated)
            }
        )

        LaunchedEffect(usbUiState.calibrated, config.stableCount, config.dimTolMm, config.weightTolG) {
            measurementState = updateMeasurementState(measurementState, usbUiState.calibrated, config)
        }

        LaunchedEffect(
            measurementState.isStable,
            measurementState.lastValues,
            config.deviceToken,
            config.serverUrl,
            displayMode,
            usbUiState.rawJson // важно: чтобы в DEBUG триггерилось по RAW
        ) {
            val valuesOrNull = measurementState.lastValues

// В PROD — только стабильные валидные значения
            if (displayMode == UsbDisplayMode.PROD) {
                if (!measurementState.isStable) return@LaunchedEffect
                if (valuesOrNull == null) return@LaunchedEffect
            }

// В DEBUG — можно отправлять и без values (RAW-only)
            if (config.deviceToken.isNullOrBlank()) return@LaunchedEffect
            if (config.serverUrl.isBlank()) return@LaunchedEffect


            // ДОБАВЬ ВОТ ЭТО: троттлинг 1 раз в секунду для DEBUG
            if (displayMode == UsbDisplayMode.DEBUG) {
                val now = System.currentTimeMillis()
                if (now - lastDebugSentAt < 1000) return@LaunchedEffect
                lastDebugSentAt = now
            }

            //val key = measurementKey(values)
            val key = if (valuesOrNull != null) {
                measurementKey(valuesOrNull)
            } else {
                // чтобы не спамить одинаковым RAW
                "raw:" + (usbUiState.rawJson?.hashCode()?.toString() ?: "null")
            }

            if (key == lastSentMeasurementKey) return@LaunchedEffect

            val result = pushStandMeasurement(
                context = context,
                cfg = config,
                values = valuesOrNull,
                displayMode = displayMode,
                uiState = usbUiState,
                measurementState = measurementState
            )
            if (result.ok) {
                lastSentMeasurementKey = key
                //scope.launch { snackbarHostState.showSnackbar("Измерение отправлено") }
            } else {
                if (displayMode == UsbDisplayMode.PROD) {
                    scope.launch {
                        snackbarHostState.showSnackbar(
                            result.errorMessage ?: "Ошибка отправки"
                        )
                    }
                } else {
                    // DEBUG — не спамим UI, только лог
                    val s = "[HTTP ERROR] ${result.errorMessage ?: "unknown"}"
                    val updated = (listOf(s) + usbUiState.logLines).take(400)
                    usbUiState = usbUiState.copy(logLines = updated)

                }
            }
        }

        Scaffold(
            topBar = {
                StandTopBar(
                    displayMode = displayMode,
                    onDisplayModeChanged = { displayMode = it },
                    onOpenSettings = { showSettings = true }
                )
            },
            snackbarHost = { SnackbarHost(snackbarHostState) }
        ) { innerPadding ->
            Box(
                modifier = Modifier
                    .padding(innerPadding)
                    .fillMaxSize()
            ) {
                if (showSettings) {
                    SettingsScreen(
                        config = config,
                        onConfigChanged = { newCfg ->
                            config = newCfg
                            repo.save(newCfg)
                        },
                        onEnrollSuccess = { token ->
                            val updated = config.copy(deviceToken = token, enrolled = true)
                            config = updated
                            repo.save(updated)
                            showSettings = false
                        },
                        onUnenroll = {
                            repo.clearEnroll()
                            config = repo.load()
                            showSettings = true
                        },
                        onClose = { if (config.enrolled && config.deviceToken != null) showSettings = false }
                    )
                } else {
                    UsbMetricsScreen(
                        displayMode = displayMode,
                        uiState = usbUiState,
                        measurementState = measurementState,
                        stableCycles = config.stableCount,
                        onOpenSettings = { showSettings = true }
                    )
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun StandTopBar(
    displayMode: UsbDisplayMode,
    onDisplayModeChanged: (UsbDisplayMode) -> Unit,
    onOpenSettings: () -> Unit
) {
    TopAppBar(
        title = { Text("Stand") },
        actions = {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(if (displayMode == UsbDisplayMode.DEBUG) "Debug" else "Prod")
                Spacer(Modifier.width(8.dp))
                Switch(
                    checked = displayMode == UsbDisplayMode.DEBUG,
                    onCheckedChange = {
                        onDisplayModeChanged(if (it) UsbDisplayMode.DEBUG else UsbDisplayMode.PROD)
                    }
                )
                IconButton(onClick = onOpenSettings) {
                    Icon(Icons.Default.Settings, contentDescription = "Настройки")
                }
            }
        }
    )
}

@Composable
fun UsbSerialBinder(
    enabled: Boolean,
    onRawLine: (String) -> Unit,
    onJson: (JSONObject) -> Unit,
    onStatus: (String) -> Unit
) {
    val context = LocalContext.current
    val usbManager = remember { context.getSystemService(Context.USB_SERVICE) as UsbManager }
    val scope = rememberCoroutineScope()

    var port: UsbSerialPort? by remember { mutableStateOf(null) }
    var readerJob: Job? by remember { mutableStateOf(null) }

    fun closePort() {
        readerJob?.cancel()
        readerJob = null
        try { port?.close() } catch (_: Exception) {}
        port = null
    }

    fun startReader(p: UsbSerialPort) {
        readerJob?.cancel()
        readerJob = scope.launch(Dispatchers.IO) {
            val buf = ByteArray(1024)
            val sb = StringBuilder()

            while (true) {
                val n = try {
                    p.read(buf, 200)
                } catch (e: Exception) {
                    withContext(Dispatchers.Main) { onStatus("Чтение упало: ${e.message}") }
                    break
                }

                if (n > 0) {
                    val chunk = String(buf, 0, n, Charset.forName("UTF-8"))
                    sb.append(chunk)

                    while (true) {
                        val idx = sb.indexOf("\n")
                        if (idx < 0) break
                        val line = sb.substring(0, idx).trim()
                        sb.delete(0, idx + 1)

                        if (line.isNotEmpty()) {
                            withContext(Dispatchers.Main) {
                                onRawLine(line)
                                try {
                                    val obj = JSONObject(line)
                                    onJson(obj)
                                } catch (_: Exception) {
                                    // ignore non-JSON lines
                                }
                            }
                        }
                    }
                } else {
                    delay(10)
                }
            }
        }
    }

    fun pickFirstSupportedDevice(): UsbDevice? {
        val drivers = UsbSerialProber.getDefaultProber().findAllDrivers(usbManager)
        return drivers.firstOrNull()?.device
    }

    fun requestPermission(dev: UsbDevice) {
        val intent = Intent(UsbAttachReceiver.USB_PERMISSION_ACTION).apply {
            setPackage(context.packageName)
        }

        val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_MUTABLE
        } else {
            PendingIntent.FLAG_UPDATE_CURRENT
        }

        val pi = PendingIntent.getBroadcast(context, 0, intent, flags)
        usbManager.requestPermission(dev, pi)
        onStatus("Запросил доступ к USB…")
    }

    DisposableEffect(enabled) {
        if (!enabled) {
            closePort()
            onDispose { }
        } else {
            val receiver = object : BroadcastReceiver() {
                override fun onReceive(ctx: Context, intent: Intent) {
                    when (intent.action) {
                        UsbAttachReceiver.USB_PERMISSION_ACTION -> {
                            val dev: UsbDevice? = if (Build.VERSION.SDK_INT >= 33) {
                                intent.getParcelableExtra(UsbManager.EXTRA_DEVICE, UsbDevice::class.java)
                            } else {
                                @Suppress("DEPRECATION")
                                intent.getParcelableExtra(UsbManager.EXTRA_DEVICE)
                            }

                            val granted = intent.getBooleanExtra(UsbManager.EXTRA_PERMISSION_GRANTED, false)
                            if (!granted || dev == null) {
                                onStatus("Доступ к USB не дали")
                                return
                            }

                            val drivers = UsbSerialProber.getDefaultProber().findAllDrivers(usbManager)
                            val driver = drivers.firstOrNull { it.device.deviceId == dev.deviceId }
                            if (driver == null) {
                                onStatus("Драйвер не найден")
                                return
                            }

                            val connection = usbManager.openDevice(driver.device)
                            if (connection == null) {
                                onStatus("openDevice=null (нет доступа?)")
                                return
                            }

                            val p = driver.ports.firstOrNull()
                            if (p == null) {
                                onStatus("Нет serial ports у драйвера")
                                return
                            }

                            try {
                                p.open(connection)
                                p.setParameters(115200, 8, UsbSerialPort.STOPBITS_1, UsbSerialPort.PARITY_NONE)
                                closePort()
                                port = p
                                onStatus("Подключено: vid=${dev.vendorId} pid=${dev.productId} 115200 8N1")
                                startReader(p)
                            } catch (e: Exception) {
                                onStatus("Ошибка открытия порта: ${e.message}")
                                try { p.close() } catch (_: Exception) {}
                            }
                        }

                        UsbManager.ACTION_USB_DEVICE_ATTACHED -> {
                            onStatus("USB подключен")
                            pickFirstSupportedDevice()?.let { dev ->
                                if (usbManager.hasPermission(dev)) {
                                    val drivers = UsbSerialProber.getDefaultProber().findAllDrivers(usbManager)
                                    val driver = drivers.firstOrNull { it.device.deviceId == dev.deviceId }
                                    if (driver == null) {
                                        onStatus("Драйвер не найден")
                                    } else {
                                        val connection = usbManager.openDevice(driver.device)
                                        if (connection == null) {
                                            onStatus("openDevice=null (нет доступа?)")
                                        } else {
                                            val p = driver.ports.firstOrNull()
                                            if (p == null) {
                                                onStatus("Нет serial ports у драйвера")
                                            } else {
                                                try {
                                                    p.open(connection)
                                                    p.setParameters(115200, 8, UsbSerialPort.STOPBITS_1, UsbSerialPort.PARITY_NONE)
                                                    closePort()
                                                    port = p
                                                    onStatus("Подключено (permission уже был): vid=${dev.vendorId} pid=${dev.productId}")
                                                    startReader(p)
                                                } catch (e: Exception) {
                                                    onStatus("Ошибка открытия порта: ${e.message}")
                                                    try { p.close() } catch (_: Exception) {}
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    requestPermission(dev)
                                }
                            }
                        }

                        UsbManager.ACTION_USB_DEVICE_DETACHED -> {
                            onStatus("USB отключен")
                            closePort()
                        }
                    }
                }
            }

            val filter = IntentFilter().apply {
                addAction(UsbAttachReceiver.USB_PERMISSION_ACTION)
                addAction(UsbManager.ACTION_USB_DEVICE_ATTACHED)
                addAction(UsbManager.ACTION_USB_DEVICE_DETACHED)
            }

            if (Build.VERSION.SDK_INT >= 33) {
                context.registerReceiver(receiver, filter, Context.RECEIVER_NOT_EXPORTED)
            } else {
                @Suppress("DEPRECATION")
                context.registerReceiver(receiver, filter)
            }

            pickFirstSupportedDevice()?.let { requestPermission(it) }

            onDispose {
                try { context.unregisterReceiver(receiver) } catch (_: Exception) {}
                closePort()
            }
        }
    }
}

private const val SIZE_TOLERANCE_MM = 0.5

private fun mergeWithTolerance(prev: Double?, next: Double?, tol: Double): Double? {
    if (next == null) return prev
    if (prev == null) return next
    return if (abs(prev - next) <= tol) prev else next
}
private fun generateQrBitmap(content: String, size: Int = 512): ImageBitmap? {
    return try {
        val bitMatrix = QRCodeWriter().encode(content, BarcodeFormat.QR_CODE, size, size)
        val bmp = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888)
        for (x in 0 until size) {
            for (y in 0 until size) {
                bmp.setPixel(x, y, if (bitMatrix[x, y]) 0xFF000000.toInt() else 0xFFFFFFFF.toInt())
            }
        }
        bmp.asImageBitmap()
    } catch (_: Exception) {
        null
    }
}
private fun mergeIntWithTolerance(prev: Int?, next: Int?, tol: Int): Int? {
    if (next == null) return prev
    if (prev == null) return next
    return if (abs(prev - next) <= tol) prev else next
}

enum class UsbDisplayMode { DEBUG, PROD }

data class UsbRawMetrics(
    val scannerToWallMm: Int? = null,
    val weightGrams: Int? = null,
    val widthMm: Double? = null,
    val depthMm: Double? = null,
    val heightMm: Double? = null,
    val scaleZeroOffsetG: Int? = null,
    val scaleFactor: Double? = null
)

data class ScaleCalibration(
    val zeroOffsetG: Int? = null,
    val factor: Double? = null
)

data class UsbCalibratedMetrics(
    val scannerToWallMm: Int? = null,
    val weightGrams: Int? = null,
    val widthMm: Double? = null,
    val depthMm: Double? = null,
    val heightMm: Double? = null,
    val scaleCalibration: ScaleCalibration = ScaleCalibration()
)

data class UsbUiState(
    val rawJson: String? = null,
    val rawMetrics: UsbRawMetrics = UsbRawMetrics(),
    val logLines: List<String> = emptyList(),   // <-- вместо logText
    val calibrated: UsbCalibratedMetrics = UsbCalibratedMetrics()
)

data class MeasurementValues(
    val widthMm: Double,
    val depthMm: Double,
    val heightMm: Double,
    val weightGrams: Int
)

data class MeasurementState(
    val lastValues: MeasurementValues? = null,
    val stableCount: Int = 0,
    val isStable: Boolean = false
)

fun buildMeasurementValues(calibrated: UsbCalibratedMetrics): MeasurementValues? {
    val w = calibrated.widthMm
    val d = calibrated.depthMm
    val h = calibrated.heightMm
    val weight = calibrated.weightGrams
    if (w == null || d == null || h == null || weight == null) return null
    if (w <= 0.0 || d <= 0.0 || h <= 0.0 || weight <= 0) return null
    return MeasurementValues(w, d, h, weight)
}

fun updateMeasurementState(
    previous: MeasurementState,
    calibrated: UsbCalibratedMetrics,
    cfg: DeviceConfig
): MeasurementState {
    val current = buildMeasurementValues(calibrated) ?: return previous.copy(
        stableCount = 0,
        isStable = false
    )

    val previousValues = previous.lastValues
    val stableCount = if (previousValues != null && isWithinTolerance(previousValues, current, cfg)) {
        previous.stableCount + 1
    } else {
        1
    }

    val isStable = stableCount >= cfg.stableCount

    return MeasurementState(
        lastValues = current,
        stableCount = stableCount,
        isStable = isStable
    )
}

fun isWithinTolerance(previous: MeasurementValues, current: MeasurementValues, cfg: DeviceConfig): Boolean {
    val weightDelta = abs(previous.weightGrams - current.weightGrams)
    val widthDelta = abs(previous.widthMm - current.widthMm)
    val depthDelta = abs(previous.depthMm - current.depthMm)
    val heightDelta = abs(previous.heightMm - current.heightMm)

    return weightDelta <= cfg.weightTolG &&
        widthDelta <= cfg.dimTolMm &&
        depthDelta <= cfg.dimTolMm &&
        heightDelta <= cfg.dimTolMm
}

fun formatCmFromMm(valueMm: Double?): String =
    valueMm?.let { "%.1f см".format(it / 10.0) } ?: "—"

fun formatCmFromMm(valueMm: Int?): String =
    valueMm?.let { "%.1f см".format(it / 10.0) } ?: "—"

fun formatGrams(value: Int?): String = value?.let { "$it г" } ?: "—"
fun formatKgFromGrams(value: Int?): String = value?.let { "%.3f кг".format(it / 1000.0) } ?: "—"
fun formatFactor(value: Double?): String = value?.let { "%.4f".format(it) } ?: "—"

private fun quantize(value: Double, step: Double): Long = kotlin.math.round(value / step).toLong()
private fun quantize(value: Int, step: Int): Int = ((value + step / 2) / step) * step

private fun measurementKey(values: MeasurementValues): String {
    val wQ = quantize(values.widthMm, 0.5)
    val dQ = quantize(values.depthMm, 0.5)
    val hQ = quantize(values.heightMm, 0.5)
    val weightQ = quantize(values.weightGrams, 10)
    return "$weightQ:$wQ:$dQ:$hQ"
}

data class StandMeasurementPushResult(
    val ok: Boolean,
    val errorMessage: String?
)

data class UsbDeviation(
    val weightDeltaG: Int? = null,
    val sizeDeltaMm: Double? = null
)

fun computeUsbDeviation(
    raw: UsbRawMetrics,
    calibrated: UsbCalibratedMetrics
): UsbDeviation {
    val weightDelta = if (raw.weightGrams != null && calibrated.weightGrams != null) {
        abs(raw.weightGrams - calibrated.weightGrams)
    } else {
        null
    }

    val sizeDiffs = listOf(
        raw.widthMm?.let { r -> calibrated.widthMm?.let { c -> abs(r - c) } },
        raw.depthMm?.let { r -> calibrated.depthMm?.let { c -> abs(r - c) } },
        raw.heightMm?.let { r -> calibrated.heightMm?.let { c -> abs(r - c) } }
    ).filterNotNull()

    val sizeDelta = if (sizeDiffs.isNotEmpty()) sizeDiffs.maxOrNull() else null

    return UsbDeviation(weightDeltaG = weightDelta, sizeDeltaMm = sizeDelta)
}

fun resolveDeviationColor(
    deviation: UsbDeviation,
    neutral: Color,
    ok: Color,
    error: Color
): Color {
    val weightDelta = deviation.weightDeltaG
    val sizeDelta = deviation.sizeDeltaMm
    if (weightDelta == null || sizeDelta == null) return neutral
    return if (weightDelta <= 30 && sizeDelta <= 5) ok else error
}

suspend fun pushStandMeasurement(
    context: Context,
    cfg: DeviceConfig,
    values: MeasurementValues?,
    displayMode: UsbDisplayMode,
    uiState: UsbUiState,
    measurementState: MeasurementState
): StandMeasurementPushResult = withContext(Dispatchers.IO) {
    val baseUrl = cfg.serverUrl.trim()
    if (baseUrl.isEmpty()) {
        return@withContext StandMeasurementPushResult(false, "Пустой Server URL")
    }
    if (cfg.deviceToken.isNullOrBlank()) {
        return@withContext StandMeasurementPushResult(false, "Нет device_token (устройство не привязано)")
    }

    val urlStr = baseUrl.trimEnd('/') + "/api/stand_measurement_push.php"
    val url = URL(urlStr)
    val conn = (url.openConnection() as HttpURLConnection)

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
            put("stand_id", cfg.deviceUid)
            put("device_uid", cfg.deviceUid)
            put("device_token", cfg.deviceToken)
            put("app_kind", "STAND")
            put("device_type", "stand")
            put("app_version", appVersion)
            put("device_name", cfg.deviceName)

            // measurements: либо объект, либо null
            if (values != null) {
                put("measurements", JSONObject().apply {
                    put("weight_g", values.weightGrams)
                    put("width_mm", values.widthMm)
                    put("depth_mm", values.depthMm)
                    put("height_mm", values.heightMm)
                })
            } else {
                put("measurements", JSONObject.NULL)
            }

            if (displayMode == UsbDisplayMode.DEBUG) {
                put("debug", true)
                put("debug_payload", JSONObject().apply {
                    uiState.rawJson?.let { put("raw_json", it) }
                    put("stable_count", measurementState.stableCount)

                    put("raw_metrics", JSONObject().apply {
                        uiState.rawMetrics.weightGrams?.let { put("weight_g", it) }
                        uiState.rawMetrics.widthMm?.let { put("width_mm", it) }
                        uiState.rawMetrics.depthMm?.let { put("depth_mm", it) }
                        uiState.rawMetrics.heightMm?.let { put("height_mm", it) }
                        uiState.rawMetrics.scannerToWallMm?.let { put("scanner_to_wall_mm", it) }
                        uiState.rawMetrics.scaleZeroOffsetG?.let { put("scale_zero_offset_g", it) }
                        uiState.rawMetrics.scaleFactor?.let { put("scale_factor", it) }
                    })

                    put("calibrated", JSONObject().apply {
                        uiState.calibrated.weightGrams?.let { put("weight_g", it) }
                        uiState.calibrated.widthMm?.let { put("width_mm", it) }
                        uiState.calibrated.depthMm?.let { put("depth_mm", it) }
                        uiState.calibrated.heightMm?.let { put("height_mm", it) }
                    })

                    put("deviation", JSONObject().apply {
                        val dev = computeUsbDeviation(uiState.rawMetrics, uiState.calibrated)
                        dev.weightDeltaG?.let { put("weight_delta_g", it) }
                        dev.sizeDeltaMm?.let { put("size_delta_mm", it) }
                    })
                })
            }
        }


        val bodyBytes = json.toString().toByteArray(Charsets.UTF_8)
        conn.outputStream.use { it.write(bodyBytes) }

        val code = conn.responseCode
        val stream: InputStream? = if (code in 200..299) conn.inputStream else conn.errorStream
        val respText = stream?.bufferedReader(Charsets.UTF_8)?.use { it.readText() } ?: ""

        if (respText.isBlank()) {
            return@withContext StandMeasurementPushResult(false, "Пустой ответ сервера (HTTP $code)")
        }

        val obj = try {
            JSONObject(respText)
        } catch (e: Exception) {
            e.printStackTrace()
            return@withContext StandMeasurementPushResult(false, "Некорректный JSON ответа")
        }

        val status = obj.optString("status", "error")
        if (status != "ok") {
            val msg = obj.optString("message", obj.optString("messages", "status != ok"))
            return@withContext StandMeasurementPushResult(false, msg)
        }

        StandMeasurementPushResult(true, null)
    } catch (e: Exception) {
        e.printStackTrace()
        StandMeasurementPushResult(false, e.message ?: "Ошибка соединения")
    } finally {
        conn.disconnect()
    }
}

data class EnrollResult(
    val ok: Boolean,
    val deviceToken: String?,
    val errorMessage: String?
)

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

        val manufacturer = Build.MANUFACTURER ?: ""
        val model = Build.MODEL ?: ""
        val modelString = (manufacturer + " " + model).trim()

        val json = JSONObject().apply {
            put("mode", "enroll")
            put("app_kind", "STAND")
            put("device_type", "stand")
            put("device_uid", cfg.deviceUid)
            put("name", cfg.deviceName)
            put("serial", "")
            put("model", modelString)
            put("app_version", appVersion)
        }

        val bodyBytes = json.toString().toByteArray(Charsets.UTF_8)
        conn.outputStream.use { os -> os.write(bodyBytes) }

        val code = conn.responseCode
        val stream = if (code in 200..299) conn.inputStream else conn.errorStream
        val respText = stream?.bufferedReader(Charsets.UTF_8)?.readText() ?: ""

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
            val msg = obj.optString("message", obj.optString("messages", "status != ok"))
            return@withContext EnrollResult(false, null, msg)
        }

        val token = obj.optString("device_token", null)
        EnrollResult(true, token, null)
    } catch (e: Exception) {
        e.printStackTrace()
        EnrollResult(false, null, e.message ?: "Ошибка соединения")
    } finally {
        conn.disconnect()
    }
}

@Composable
fun UsbMetricsScreen(
    displayMode: UsbDisplayMode,
    uiState: UsbUiState,
    measurementState: MeasurementState,
    stableCycles: Int,
    onOpenSettings: () -> Unit,
    modifier: Modifier = Modifier
) {
    val deviation = remember(uiState) { computeUsbDeviation(uiState.rawMetrics, uiState.calibrated) }
    val scrollState = rememberScrollState()

    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(scrollState)
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = if (measurementState.isStable) "Стабильно" else "Нестабильно",
                style = MaterialTheme.typography.titleLarge,
                color = if (measurementState.isStable) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.error
            )
        }

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(
                modifier = Modifier.weight(1f),
                verticalArrangement = Arrangement.spacedBy(4.dp)
            ) {
                Text("Режим: ${if (displayMode == UsbDisplayMode.DEBUG) "Debug" else "Prod"}")
                Text("Стабильность: ${measurementState.stableCount} / $stableCycles")
            }

            // Лого показываем только в PROD (в DEBUG скрываем)
            if (displayMode == UsbDisplayMode.PROD) {
                Image(
                    painter = painterResource(id = R.drawable.cc_logo),
                    contentDescription = "CargoCells",
                    modifier = Modifier
                        .height(44.dp)
                        .padding(start = 12.dp),
                    contentScale = ContentScale.Fit
                )
            }
        }


// --- PROD: большие плитки для пользователя ---
        if (displayMode == UsbDisplayMode.PROD) {
            ProdBigBlocks(uiState)

            Spacer(Modifier.height(8.dp))

            // мелкие строки оставить (если хочешь как на скрине)
            Text(
                "Отклонения: вес=—, размер=—",
                style = MaterialTheme.typography.bodySmall
            )
            val v = measurementState.lastValues
            if (measurementState.isStable && v != null) {
                val qrContent = remember(v) {
                    // что кодируем в QR: минимальный JSON
                    JSONObject().apply {
                        put("weight_g", v.weightGrams)
                        put("width_mm", v.widthMm)
                        put("depth_mm", v.depthMm)
                        put("height_mm", v.heightMm)
                        // опционально: put("ts", System.currentTimeMillis()/1000)
                    }.toString()
                }

                val qrImg = remember(qrContent) { generateQrBitmap(qrContent, 520) }

                if (qrImg != null) {
                    Card(modifier = Modifier.fillMaxWidth()) {
                        Column(modifier = Modifier.padding(12.dp)) {
                            Text("QR измерения", style = MaterialTheme.typography.titleMedium)
                            Spacer(Modifier.height(8.dp))
                            androidx.compose.foundation.Image(
                                bitmap = qrImg,
                                contentDescription = "QR",
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(260.dp)
                            )
                        }
                    }
                }
            }
        }

// --- DEBUG: твой текущий детальный блок (RAW + лог) ---
        if (displayMode == UsbDisplayMode.DEBUG) {
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = resolveDeviationColor(
                        deviation,
                        neutral = MaterialTheme.colorScheme.surfaceVariant,
                        ok = Color(0xFFC9F6D4),
                        error = Color(0xFFF8C9C9)
                    )
                )
            ) {
                Column(modifier = Modifier.padding(12.dp)) {
                    Text("RAW")
                    MetricRow("Вес (raw)", formatGrams(uiState.rawMetrics.weightGrams))
                    MetricRow("Ширина (raw)", formatCmFromMm(uiState.rawMetrics.widthMm))
                    MetricRow("Глубина (raw)", formatCmFromMm(uiState.rawMetrics.depthMm))
                    MetricRow("Высота (raw)", formatCmFromMm(uiState.rawMetrics.heightMm))
                    MetricRow("Калибровка offset", formatGrams(uiState.rawMetrics.scaleZeroOffsetG))
                    MetricRow("Калибровка factor", formatFactor(uiState.rawMetrics.scaleFactor))
                    Spacer(Modifier.height(8.dp))
                    Text("Журнал")
                    val logScroll = rememberScrollState()

                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(220.dp)
                            .verticalScroll(logScroll)
                    ) {
                        uiState.logLines.forEach { line ->
                            Text(line, style = MaterialTheme.typography.bodySmall)
                        }
                    }

                }
            }
        }
    }
}

@Composable
fun MetricRow(label: String, value: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Text(label, style = MaterialTheme.typography.bodyMedium)
        Text(value, style = MaterialTheme.typography.bodyMedium)
    }
}

@Composable
private fun BigMetricCard(
    title: String,
    value: String,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier.height(86.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant)
    ) {
        Column(
            modifier = Modifier.padding(12.dp),
            verticalArrangement = Arrangement.SpaceBetween
        ) {
            Text(title, style = MaterialTheme.typography.labelLarge)
            Text(value, style = MaterialTheme.typography.titleLarge)
        }
    }
}

@Composable
private fun ProdBigBlocks(uiState: UsbUiState) {
    Text("Итоговые значения", style = MaterialTheme.typography.titleMedium)

    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        BigMetricCard(
            title = "Вес",
            value = formatKgFromGrams(uiState.calibrated.weightGrams),
            modifier = Modifier.weight(1f)
        )
        BigMetricCard(
            title = "Ширина",
            value = formatCmFromMm(uiState.calibrated.widthMm),
            modifier = Modifier.weight(1f)
        )
    }

    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        BigMetricCard(
            title = "Глубина",
            value = formatCmFromMm(uiState.calibrated.depthMm),
            modifier = Modifier.weight(1f)
        )
        BigMetricCard(
            title = "Высота",
            value = formatCmFromMm(uiState.calibrated.heightMm),
            modifier = Modifier.weight(1f)
        )
    }
}
@Composable
fun SettingsScreen(
    config: DeviceConfig,
    onConfigChanged: (DeviceConfig) -> Unit,
    onEnrollSuccess: (String) -> Unit,
    onUnenroll: () -> Unit,
    onClose: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    var serverUrl by remember { mutableStateOf(config.serverUrl) }
    var deviceName by remember { mutableStateOf(config.deviceName) }
    var allowInsecure by remember { mutableStateOf(config.allowInsecureSsl) }
    var stableCountText by remember { mutableStateOf(config.stableCount.toString()) }
    var dimTolText by remember { mutableStateOf(config.dimTolMm.toString()) }
    var weightTolText by remember { mutableStateOf(config.weightTolG.toString()) }
    var statusText by remember { mutableStateOf("") }
    var isBusy by remember { mutableStateOf(false) }

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(16.dp)
            .verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Text("Настройки устройства", style = MaterialTheme.typography.titleLarge)

        OutlinedTextField(
            value = serverUrl,
            onValueChange = { serverUrl = it },
            label = { Text("Server URL") },
            modifier = Modifier.fillMaxWidth()
        )
        OutlinedTextField(
            value = deviceName,
            onValueChange = { deviceName = it },
            label = { Text("Имя устройства") },
            modifier = Modifier.fillMaxWidth()
        )

        Row(verticalAlignment = Alignment.CenterVertically) {
            Switch(checked = allowInsecure, onCheckedChange = { allowInsecure = it })
            Spacer(Modifier.width(8.dp))
            Text("Разрешить самоподписанный TLS")
        }

        OutlinedTextField(
            value = stableCountText,
            onValueChange = { stableCountText = it.filter { ch -> ch.isDigit() } },
            label = { Text("Стабильных циклов") },
            modifier = Modifier.fillMaxWidth()
        )
        OutlinedTextField(
            value = dimTolText,
            onValueChange = { dimTolText = it.filter { ch -> ch.isDigit() } },
            label = { Text("Допуск габаритов, мм") },
            modifier = Modifier.fillMaxWidth()
        )
        OutlinedTextField(
            value = weightTolText,
            onValueChange = { weightTolText = it.filter { ch -> ch.isDigit() } },
            label = { Text("Допуск веса, г") },
            modifier = Modifier.fillMaxWidth()
        )

        Button(
            onClick = {
                val stable = (stableCountText.toIntOrNull() ?: config.stableCount).coerceIn(1, 20)
                val dim = (dimTolText.toIntOrNull() ?: config.dimTolMm).coerceIn(0, 50)
                val weight = (weightTolText.toIntOrNull() ?: config.weightTolG).coerceIn(0, 500)

                val updated = config.copy(
                    serverUrl = serverUrl.trim(),
                    deviceName = deviceName.trim(),
                    allowInsecureSsl = allowInsecure,
                    stableCount = stable,
                    dimTolMm = dim,
                    weightTolG = weight
                )
                onConfigChanged(updated)
                statusText = "Сохранено"
            },
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Сохранить")
        }

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
                            allowInsecureSsl = allowInsecure
                        )
                        onConfigChanged(updated)
                        val result = enrollDeviceOnServer(context, updated)
                        if (result.ok && !result.deviceToken.isNullOrBlank()) {
                            statusText = "Устройство привязано"
                            onEnrollSuccess(result.deviceToken)
                        } else {
                            statusText = "Ошибка привязки: ${result.errorMessage ?: "неизвестно"}"
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

        Button(
            onClick = {
                isBusy = true
                statusText = "Отвязываю устройство…"
                scope.launch {
                    try {
                        delay(500)
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

        OutlinedButton(
            onClick = onClose,
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Закрыть")
        }

        if (statusText.isNotBlank()) {
            Text(statusText)
        }
    }
}