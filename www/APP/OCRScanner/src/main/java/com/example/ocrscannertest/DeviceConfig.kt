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
    val syncNameDict: Boolean = true     // тянуть словарь из WebView
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

        return DeviceConfig(
            serverUrl = serverUrl,
            deviceUid = uid,
            deviceName = deviceName,
            deviceToken = deviceToken,
            enrolled = enrolled,
            allowInsecureSsl = allowInsecure

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
            .putBoolean("allow_insecure_ssl", cfg.allowInsecureSsl)
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
