package com.example.scantesthard

import android.content.Intent
import android.graphics.Color
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.util.Log
import android.view.Gravity
import android.view.WindowManager
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.TextView
import androidx.activity.ComponentActivity

/**
 * Временный диагностический экран для проверки сканера без WebView/Compose/flow-логики.
 */
class MainActivity : ComponentActivity() {

    private var hsBootstrapInProgress = false
    private var lastHsBootstrapAt = 0L

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        window.setSoftInputMode(
            WindowManager.LayoutParams.SOFT_INPUT_STATE_ALWAYS_HIDDEN or
                    WindowManager.LayoutParams.SOFT_INPUT_ADJUST_NOTHING
        )

        setContentView(createSimpleScreen())
    }

    private fun createSimpleScreen(): LinearLayout {
        val root = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setBackgroundColor(Color.WHITE)
            gravity = Gravity.CENTER
            val padding = (24 * resources.displayMetrics.density).toInt()
            setPadding(padding, padding, padding, padding)
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.MATCH_PARENT
            )
        }

        val title = TextView(this).apply {
            text = "Simple diagnostic screen (no WebView)"
            textSize = 22f
            setTextColor(Color.BLACK)
            gravity = Gravity.CENTER
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.WRAP_CONTENT
            ).apply {
                bottomMargin = (16 * resources.displayMetrics.density).toInt()
            }
        }

        val input = EditText(this).apply {
            hint = "Type or scan here"
            setTextColor(Color.BLACK)
            setHintTextColor(Color.GRAY)
            setBackgroundColor(Color.WHITE)
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.WRAP_CONTENT
            )
            requestFocus()
        }

        root.addView(title)
        root.addView(input)
        return root
    }

    private fun bootstrapHsViaSplashActivity() {
        if (hsBootstrapInProgress) return
        hsBootstrapInProgress = true

        try {
            val hsIntent = Intent().apply {
                setClassName(
                    "com.hs.dcsservice",
                    "com.hs.scanbutton.SplashActivity"
                )
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                addFlags(Intent.FLAG_ACTIVITY_NO_ANIMATION)
            }
            startActivity(hsIntent)

            Handler(Looper.getMainLooper()).postDelayed({
                try {
                    val backIntent = packageManager.getLaunchIntentForPackage(packageName)?.apply {
                        addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP)
                        addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP)
                        addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                        addFlags(Intent.FLAG_ACTIVITY_NO_ANIMATION)
                    }
                    if (backIntent != null) {
                        startActivity(backIntent)
                    }
                } catch (e: Exception) {
                    Log.e("HS_BOOTSTRAP", "Failed to return to app", e)
                } finally {
                    hsBootstrapInProgress = false
                }
            }, 3000)
        } catch (e: Exception) {
            hsBootstrapInProgress = false
            Log.e("HS_BOOTSTRAP", "Failed to start HS SplashActivity", e)
        }
    }

    override fun onResume() {
        super.onResume()

        val now = System.currentTimeMillis()
        if (!hsBootstrapInProgress && now - lastHsBootstrapAt > 5_000L) {
            lastHsBootstrapAt = now
            Handler(Looper.getMainLooper()).postDelayed({
                bootstrapHsViaSplashActivity()
            }, 250)
        }
    }
}