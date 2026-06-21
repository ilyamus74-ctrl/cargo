package com.example.ocrscannertest  // свой пакет

import android.content.Context
import android.provider.Settings
import java.util.UUID

data class DeviceConfig(
    val serverUrl: String,
    val deviceUid: String,
    val deviceName: String,
    val deviceToken: String?,
    val enrolled: Boolean,
    val allowInsecureSsl: Boolean = false,
    val useRemoteOcr: Boolean = false,   // локальный/удалённый парсер
    val liveScanEnabled: Boolean = false, // live распознавание в превью
    val syncNameDict: Boolean = true,    // тянуть словарь из WebView
    val debugToasts: Boolean = false,    // показать debug toasts
    val cameraModeEnabled: Boolean = false, // показывать пресеты зума на экране сканера
    val ocrSendLabelPhoto: Boolean = false, // отправлять фото лейбла после OCR
    val ocrLabelPhotoMaxWidth: Int = 1280,
    val ocrLabelPhotoJpegQuality: Int = 75,
    val softKeyboardHoldEnabled: Boolean = false,
    val softKeyboardHoldDisableWebKeyboardJs: Boolean = true,
    val softKeyboardHoldToggleScanCode: Int = 229,
    val softKeyboardHoldToggleKeyCode: Int = 0
)

class DeviceConfigRepository(private val context: Context) {

    private val prefs = context.getSharedPreferences("device_config", Context.MODE_PRIVATE)

    fun load(): DeviceConfig {
        val savedUid = prefs.getString("device_uid", null)
        val uid = savedUid ?: generateAndSaveUid()

        val serverUrl = prefs.getString("server_url", "") ?: ""
        val deviceName = prefs.getString("device_name", "") ?: ""
        val deviceToken = prefs.getString("device_token", null)
        val enrolled = prefs.getBoolean("enrolled", false)
        val allowInsecure = prefs.getBoolean("allow_insecure_ssl", false)
        val useRemoteOcr = prefs.getBoolean("use_remote_ocr", false)
        val liveScanEnabled = prefs.getBoolean("live_scan_enabled", false)
        val syncNameDict = prefs.getBoolean("sync_name_dict", true)
        val debugToasts = prefs.getBoolean("debug_toasts", false)
        val cameraModeEnabled = prefs.getBoolean("camera_mode_enabled", false)
        val ocrSendLabelPhoto = prefs.getBoolean("ocr_send_label_photo", false)
        val ocrLabelPhotoMaxWidth = prefs.getInt("ocr_label_photo_max_width", 1280).coerceIn(640, 2000)
        val ocrLabelPhotoJpegQuality = prefs.getInt("ocr_label_photo_jpeg_quality", 75).coerceIn(50, 90)
        val softKeyboardHoldEnabled = prefs.getBoolean("soft_keyboard_hold_enabled", false)
        val softKeyboardHoldDisableWebKeyboardJs = prefs.getBoolean("soft_keyboard_hold_disable_web_keyboard_js", true)
        val softKeyboardHoldToggleScanCode = prefs.getInt("soft_keyboard_hold_toggle_scan_code", 229)
        val softKeyboardHoldToggleKeyCode = prefs.getInt("soft_keyboard_hold_toggle_key_code", 0)

        return DeviceConfig(
            serverUrl = serverUrl,
            deviceUid = uid,
            deviceName = deviceName,
            deviceToken = deviceToken,
            enrolled = enrolled,
            allowInsecureSsl = allowInsecure,
            liveScanEnabled = liveScanEnabled,
            useRemoteOcr = useRemoteOcr,
            syncNameDict = syncNameDict,
            debugToasts = debugToasts,
            cameraModeEnabled = cameraModeEnabled,
            ocrSendLabelPhoto = ocrSendLabelPhoto,
            ocrLabelPhotoMaxWidth = ocrLabelPhotoMaxWidth,
            ocrLabelPhotoJpegQuality = ocrLabelPhotoJpegQuality,
            softKeyboardHoldEnabled = softKeyboardHoldEnabled,
            softKeyboardHoldDisableWebKeyboardJs = softKeyboardHoldDisableWebKeyboardJs,
            softKeyboardHoldToggleScanCode = softKeyboardHoldToggleScanCode,
            softKeyboardHoldToggleKeyCode = softKeyboardHoldToggleKeyCode
        )
    }



    private fun generateAndSaveUid(): String {
        val androidId = Settings.Secure.getString(
            context.contentResolver,
            Settings.Secure.ANDROID_ID
        ) ?: ""
        val uid = "dev-" + UUID.randomUUID().toString() + "-" + androidId
        prefs.edit().putString("device_uid", uid).apply()
        return uid
    }

    fun save(cfg: DeviceConfig) {
        prefs.edit()
            .putString("server_url", cfg.serverUrl)
            .putString("device_name", cfg.deviceName)
            .putString("device_token", cfg.deviceToken)
            .putBoolean("enrolled", cfg.enrolled)
            .putBoolean("use_remote_ocr", cfg.useRemoteOcr)
            .putBoolean("live_scan_enabled", cfg.liveScanEnabled)
            .putBoolean("sync_name_dict", cfg.syncNameDict)
            .putBoolean("debug_toasts", cfg.debugToasts)
            .putBoolean("camera_mode_enabled", cfg.cameraModeEnabled)
            .putBoolean("ocr_send_label_photo", cfg.ocrSendLabelPhoto)
            .putInt("ocr_label_photo_max_width", cfg.ocrLabelPhotoMaxWidth.coerceIn(640, 2000))
            .putInt("ocr_label_photo_jpeg_quality", cfg.ocrLabelPhotoJpegQuality.coerceIn(50, 90))
            .putBoolean("soft_keyboard_hold_enabled", cfg.softKeyboardHoldEnabled)
            .putBoolean("soft_keyboard_hold_disable_web_keyboard_js", cfg.softKeyboardHoldDisableWebKeyboardJs)
            .putInt("soft_keyboard_hold_toggle_scan_code", cfg.softKeyboardHoldToggleScanCode)
            .putInt("soft_keyboard_hold_toggle_key_code", cfg.softKeyboardHoldToggleKeyCode)
            .apply()
    }

    fun clearEnroll() {
        prefs.edit()
            .putString("server_url", "")
            .putString("device_name", "")
            .remove("device_token")
            .putBoolean("enrolled", false)
            .apply()
    }
}
