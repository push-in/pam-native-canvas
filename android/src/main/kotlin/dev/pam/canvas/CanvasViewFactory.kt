package dev.pam.canvas

import android.content.Context
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.view.MotionEvent
import android.view.View
import dev.pam.nativeapp.protocol.WireMap
import dev.pam.nativeapp.protocol.WireValue
import dev.pam.nativeapp.views.NativeViewFactory
import org.json.JSONArray

class CanvasViewFactory(@Suppress("UNUSED_PARAMETER") context: Context) : NativeViewFactory {
    override fun create(context: Context, emit: (ByteArray) -> Unit): View = PamCanvas(context, emit)
    override fun update(view: View, properties: Map<String, WireValue>) = (view as PamCanvas).update(properties)
    override fun release(view: View) = Unit
}

private class PamCanvas(context: Context, private val emit: (ByteArray) -> Unit) : View(context) {
    private var commands = JSONArray()
    private var revision = -1L
    private val paint = Paint(Paint.ANTI_ALIAS_FLAG)

    fun update(values: Map<String, WireValue>) {
        val next = (values["revision"] as? WireValue.Integer)?.value ?: 0
        if (next == revision) return
        val json = (values["displayList"] as? WireValue.Text)?.value ?: "[]"
        commands = runCatching { JSONArray(json) }.getOrDefault(JSONArray())
        revision = next
        invalidate()
    }

    override fun onDraw(canvas: Canvas) {
        super.onDraw(canvas)
        for (index in 0 until commands.length()) {
            val command = commands.optJSONObject(index) ?: continue
            val arguments = command.optJSONArray("a") ?: JSONArray()
            runCatching { drawCommand(canvas, command.optInt("k"), arguments) }
        }
    }

    private fun drawCommand(canvas: Canvas, kind: Int, a: JSONArray) {
        when (kind) {
            1 -> canvas.save()
            2 -> if (canvas.saveCount > 1) canvas.restore()
            3 -> canvas.translate(number(a, 0), number(a, 1))
            4 -> canvas.rotate(number(a, 0))
            5 -> canvas.scale(number(a, 0), number(a, 1))
            6 -> canvas.clipRect(number(a, 0), number(a, 1), number(a, 0) + number(a, 2), number(a, 1) + number(a, 3))
            7 -> canvas.drawColor(color(text(a, 0)))
            8 -> { fill(text(a, 4)); canvas.drawRect(number(a, 0), number(a, 1), number(a, 0) + number(a, 2), number(a, 1) + number(a, 3), paint) }
            9 -> { stroke(text(a, 4), number(a, 5)); canvas.drawRect(number(a, 0), number(a, 1), number(a, 0) + number(a, 2), number(a, 1) + number(a, 3), paint) }
            10 -> { fill(text(a, 3)); canvas.drawCircle(number(a, 0), number(a, 1), number(a, 2), paint) }
            11 -> { stroke(text(a, 4), number(a, 5)); canvas.drawLine(number(a, 0), number(a, 1), number(a, 2), number(a, 3), paint) }
            12 -> { fill(text(a, 4)); paint.textSize = number(a, 3); canvas.drawText(text(a, 0), number(a, 1), number(a, 2), paint) }
        }
    }

    override fun onTouchEvent(event: MotionEvent): Boolean {
        val kind = when (event.actionMasked) {
            MotionEvent.ACTION_DOWN -> 1L
            MotionEvent.ACTION_MOVE -> 2L
            MotionEvent.ACTION_UP -> 3L
            else -> 4L
        }
        emit(WireMap.encode(mapOf("event" to WireValue.Integer(kind), "x" to WireValue.Decimal(event.x.toDouble()), "y" to WireValue.Decimal(event.y.toDouble()))))
        return true
    }

    private fun fill(value: String) { paint.style = Paint.Style.FILL; paint.color = color(value) }
    private fun stroke(value: String, width: Float) { paint.style = Paint.Style.STROKE; paint.strokeWidth = width.coerceAtLeast(0f); paint.color = color(value) }
    private fun color(value: String): Int = runCatching { Color.parseColor(value) }.getOrDefault(Color.TRANSPARENT)
    private fun number(arguments: JSONArray, index: Int): Float = arguments.optDouble(index).toFloat()
    private fun text(arguments: JSONArray, index: Int): String = arguments.optString(index)
}
