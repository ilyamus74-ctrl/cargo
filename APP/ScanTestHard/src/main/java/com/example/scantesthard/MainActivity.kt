package com.example.scantesthard

import android.annotation.SuppressLint
import android.content.Intent
import android.graphics.Color
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.util.Log
import android.view.Gravity
import android.view.WindowManager
import android.webkit.WebView
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.TextView
import androidx.activity.ComponentActivity

private enum class DiagnosticMode {
    SIMPLE_NO_WEBVIEW,
    SIMPLE_WEBVIEW
}

/**
 * Временный диагностический экран для проверки сканера.
 * */
class MainActivity : ComponentActivity() {

    private var hsBootstrapInProgress = false
    private var lastHsBootstrapAt = 0L
    private var diagnosticMode = DiagnosticMode.SIMPLE_NO_WEBVIEW


    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        window.setSoftInputMode(
        WindowManager.LayoutParams.SOFT_INPUT_STATE_ALWAYS_HIDDEN or
                    WindowManager.LayoutParams.SOFT_INPUT_ADJUST_NOTHING
        )

        renderCurrentScreen()
    }
    private fun renderCurrentScreen() {
        setContentView(
            when (diagnosticMode) {
                DiagnosticMode.SIMPLE_NO_WEBVIEW -> createSimpleScreen()
                DiagnosticMode.SIMPLE_WEBVIEW -> createSimpleWebViewScreen()
            }
        )
    }

    private fun createRootLayout(): LinearLayout {
        return LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setBackgroundColor(Color.WHITE)
            val padding = (20 * resources.displayMetrics.density).toInt()
            setPadding(padding, padding, padding, padding)
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.MATCH_PARENT
            )
        }

    }

    private fun createModeSwitcher(root: LinearLayout) {
        val switcher = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.WRAP_CONTENT
            ).apply {
                bottomMargin = (16 * resources.displayMetrics.density).toInt()
            }
        }

        val noWebViewButton = Button(this).apply {
            text = "Simple diagnostic (no webview)"
            isAllCaps = false
            setOnClickListener {
                diagnosticMode = DiagnosticMode.SIMPLE_NO_WEBVIEW
                renderCurrentScreen()
            }
        }

        val webViewButton = Button(this).apply {
            text = "Simple WebView diagnostic"
            isAllCaps = false
            setOnClickListener {
                diagnosticMode = DiagnosticMode.SIMPLE_WEBVIEW
                renderCurrentScreen()
            }
        }

        val buttonParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f).apply {
            marginEnd = (8 * resources.displayMetrics.density).toInt()
        }
        switcher.addView(noWebViewButton, buttonParams)
        switcher.addView(
            webViewButton,
            LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f)
        )

        root.addView(switcher)
    }

    private fun createSimpleScreen(): LinearLayout {
        val root = createRootLayout()
        createModeSwitcher(root)
        val title = TextView(this).apply {
            text = "Simple diagnostic screen (no webview)"
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
            textSize = 24f
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

    @SuppressLint("SetJavaScriptEnabled")
    private fun createSimpleWebViewScreen(): LinearLayout {
        val root = createRootLayout()
        createModeSwitcher(root)

        val webView = WebView(this).apply {
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                0,
                1f
            )

            settings.javaScriptEnabled = true
            settings.domStorageEnabled = true

            loadDataWithBaseURL(
                null,
                """
                <html>
                  <body style="margin:20px;">
                    <h2>Simple WebView diagnostic screen</h2>
                    <input id="code" autofocus style="font-size:32px; width:100%; height:56px;" />
                  </body>
                </html>
                """.trimIndent(),
                "text/html",
                "UTF-8",
                null
            )
        }

        root.addView(webView)
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