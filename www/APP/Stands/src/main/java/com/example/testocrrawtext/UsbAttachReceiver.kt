package com.example.testocrrawtext

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.hardware.usb.UsbDevice
import android.hardware.usb.UsbManager

class UsbAttachReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == UsbManager.ACTION_USB_DEVICE_ATTACHED) {

            val device: UsbDevice? = if (android.os.Build.VERSION.SDK_INT >= 33) {
                intent.getParcelableExtra(UsbManager.EXTRA_DEVICE, UsbDevice::class.java)
            } else {
                @Suppress("DEPRECATION")
                intent.getParcelableExtra(UsbManager.EXTRA_DEVICE)
            }

            // можно логнуть, но можно и оставить пустым
            // Log.d("USB", "ATTACHED: ${device?.deviceName} vid=${device?.vendorId} pid=${device?.productId}")
        }
    }
}