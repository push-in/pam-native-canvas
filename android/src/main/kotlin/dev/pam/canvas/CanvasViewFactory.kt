package dev.pam.canvas

import android.content.Context
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.DashPathEffect
import android.graphics.LinearGradient
import android.graphics.Paint
import android.graphics.Path
import android.graphics.RectF
import android.graphics.Shader
import android.graphics.Typeface
import android.os.Build
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

/**
 * Replays the JSON display list `[{"k":kind,"a":[...]}]` on an Android Canvas.
 *
 * Kinds 1–12 are the 0.1.0 contract, 13–25 were added in 0.2.0. When the
 * `density` host property is 1 the scene is in dp: drawing is scaled by the
 * display density and pointer coordinates are reported in dp.
 */
private class PamCanvas(context: Context, private val emit: (ByteArray) -> Unit) : View(context) {
    private var commands = JSONArray()
    private var revision = -1L
    private var densityIndependent = true
    private val paint = Paint(Paint.ANTI_ALIAS_FLAG)
    private val path = Path()
    private val rect = RectF()
    private val shadowStack = ArrayDeque<ShadowState>()
    private val layerStack = ArrayDeque<Int>()
    private var shadow = ShadowState.NONE

    private data class ShadowState(val color: Int, val blur: Float, val dx: Float, val dy: Float) {
        companion object { val NONE = ShadowState(Color.TRANSPARENT, 0f, 0f, 0f) }
    }

    fun update(values: Map<String, WireValue>) {
        densityIndependent = ((values["density"] as? WireValue.Integer)?.value ?: 1L) != 0L
        val next = (values["revision"] as? WireValue.Integer)?.value ?: 0
        if (next == revision) return
        val json = (values["displayList"] as? WireValue.Text)?.value ?: "[]"
        commands = runCatching { JSONArray(json) }.getOrDefault(JSONArray())
        revision = next
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.P && usesShadow()) {
            setLayerType(LAYER_TYPE_SOFTWARE, null)
        }
        invalidate()
    }

    private fun usesShadow(): Boolean {
        for (index in 0 until commands.length()) {
            if (commands.optJSONObject(index)?.optInt("k") == 24) return true
        }
        return false
    }

    private fun scale(): Float = if (densityIndependent) resources.displayMetrics.density else 1f

    override fun onDraw(canvas: Canvas) {
        super.onDraw(canvas)
        val checkpoint = canvas.save()
        canvas.scale(scale(), scale())
        val floor = canvas.saveCount
        shadowStack.clear()
        layerStack.clear()
        shadow = ShadowState.NONE
        for (index in 0 until commands.length()) {
            val command = commands.optJSONObject(index) ?: continue
            val arguments = command.optJSONArray("a") ?: JSONArray()
            runCatching { drawCommand(canvas, floor, command.optInt("k"), arguments) }
        }
        canvas.restoreToCount(checkpoint)
    }

    private fun drawCommand(canvas: Canvas, floor: Int, kind: Int, a: JSONArray) {
        when (kind) {
            1 -> save(canvas)
            2 -> restore(canvas, floor)
            3 -> canvas.translate(number(a, 0), number(a, 1))
            4 -> canvas.rotate(number(a, 0))
            5 -> canvas.scale(number(a, 0), number(a, 1))
            6 -> canvas.clipRect(number(a, 0), number(a, 1), number(a, 0) + number(a, 2), number(a, 1) + number(a, 3))
            7 -> canvas.drawColor(parseColor(text(a, 0)))
            8 -> { fill(text(a, 4)); canvas.drawRect(rect(a, 0), paint) }
            9 -> { stroke(text(a, 4), number(a, 5)); canvas.drawRect(rect(a, 0), paint) }
            10 -> { fill(text(a, 3)); canvas.drawCircle(number(a, 0), number(a, 1), number(a, 2), paint) }
            11 -> { stroke(text(a, 4), number(a, 5)); canvas.drawLine(number(a, 0), number(a, 1), number(a, 2), number(a, 3), paint) }
            12 -> drawText(canvas, text(a, 0), number(a, 1), number(a, 2), number(a, 3), text(a, 4), 1, 1)
            13 -> { fill(text(a, 5)); canvas.drawRoundRect(rect(a, 0), number(a, 4), number(a, 4), paint) }
            14 -> { stroke(text(a, 5), number(a, 6)); canvas.drawRoundRect(rect(a, 0), number(a, 4), number(a, 4), paint) }
            15 -> drawArc(canvas, a)
            16 -> drawSector(canvas, a)
            17 -> drawPolyline(canvas, a)
            18 -> { fill(text(a, 1)); canvas.drawPath(points(text(a, 0), close = true), paint) }
            19 -> drawPath(canvas, a)
            20 -> drawGradientRect(canvas, a)
            21 -> drawGradientPath(canvas, a)
            22 -> alpha(canvas, number(a, 0))
            23 -> drawText(canvas, text(a, 0), number(a, 1), number(a, 2), number(a, 3), text(a, 4), a.optInt(5, 1), a.optInt(6, 1))
            24 -> shadow = ShadowState(parseColor(text(a, 0)), number(a, 1).coerceAtLeast(0f), number(a, 2), number(a, 3))
            25 -> drawDashedLine(canvas, a)
        }
    }

    // -- state ----------------------------------------------------------------

    private fun save(canvas: Canvas) {
        canvas.save()
        shadowStack.addLast(shadow)
        layerStack.addLast(0)
    }

    private fun restore(canvas: Canvas, floor: Int) {
        if (layerStack.isEmpty()) return
        val layers = layerStack.removeLast()
        repeat(layers + 1) { if (canvas.saveCount > floor) canvas.restore() }
        shadow = shadowStack.removeLastOrNull() ?: ShadowState.NONE
    }

    private fun alpha(canvas: Canvas, opacity: Float) {
        if (layerStack.isEmpty()) return
        canvas.saveLayerAlpha(null, (opacity.coerceIn(0f, 1f) * 255f).toInt())
        layerStack.addLast(layerStack.removeLast() + 1)
    }

    // -- shapes ---------------------------------------------------------------

    private fun drawArc(canvas: Canvas, a: JSONArray) {
        stroke(text(a, 5), number(a, 6))
        paint.strokeCap = cap(a.optInt(7, 1))
        val radius = number(a, 2)
        rect.set(number(a, 0) - radius, number(a, 1) - radius, number(a, 0) + radius, number(a, 1) + radius)
        canvas.drawArc(rect, number(a, 3) - 90f, number(a, 4), false, paint)
        paint.strokeCap = Paint.Cap.BUTT
    }

    private fun drawSector(canvas: Canvas, a: JSONArray) {
        fill(text(a, 6))
        val cx = number(a, 0)
        val cy = number(a, 1)
        val outer = number(a, 2)
        val inner = number(a, 3).coerceIn(0f, outer)
        val start = number(a, 4) - 90f
        val sweep = number(a, 5)
        path.reset()
        rect.set(cx - outer, cy - outer, cx + outer, cy + outer)
        if (inner <= 0f) {
            path.moveTo(cx, cy)
            path.arcTo(rect, start, sweep)
        } else {
            path.arcTo(rect, start, sweep)
            rect.set(cx - inner, cy - inner, cx + inner, cy + inner)
            path.arcTo(rect, start + sweep, -sweep)
        }
        path.close()
        canvas.drawPath(path, paint)
    }

    private fun drawPolyline(canvas: Canvas, a: JSONArray) {
        stroke(text(a, 1), number(a, 2))
        paint.strokeCap = cap(a.optInt(3, 2))
        paint.strokeJoin = join(a.optInt(4, 2))
        canvas.drawPath(points(text(a, 0), close = false), paint)
        paint.strokeCap = Paint.Cap.BUTT
        paint.strokeJoin = Paint.Join.MITER
    }

    private fun drawPath(canvas: Canvas, a: JSONArray) {
        val shape = parsePath(text(a, 0))
        if (a.optInt(3, 1) == 2) {
            stroke(text(a, 1), number(a, 2))
            paint.strokeCap = Paint.Cap.ROUND
            paint.strokeJoin = Paint.Join.ROUND
            canvas.drawPath(shape, paint)
            paint.strokeCap = Paint.Cap.BUTT
            paint.strokeJoin = Paint.Join.MITER
        } else {
            fill(text(a, 1))
            canvas.drawPath(shape, paint)
        }
    }

    private fun drawGradientRect(canvas: Canvas, a: JSONArray) {
        fill("#00000000")
        paint.color = Color.WHITE
        paint.shader = gradient(number(a, 5), number(a, 6), number(a, 7), number(a, 8), text(a, 9), text(a, 10))
        canvas.drawRoundRect(rect(a, 0), number(a, 4), number(a, 4), paint)
        paint.shader = null
    }

    private fun drawGradientPath(canvas: Canvas, a: JSONArray) {
        val shape = parsePath(text(a, 0))
        fill("#00000000")
        paint.color = Color.WHITE
        paint.shader = gradient(number(a, 1), number(a, 2), number(a, 3), number(a, 4), text(a, 5), text(a, 6))
        canvas.drawPath(shape, paint)
        paint.shader = null
    }

    private fun drawDashedLine(canvas: Canvas, a: JSONArray) {
        stroke(text(a, 4), number(a, 5))
        val dash = number(a, 6).coerceAtLeast(0.1f)
        val gap = number(a, 7).coerceAtLeast(0.1f)
        paint.pathEffect = DashPathEffect(floatArrayOf(dash, gap), 0f)
        canvas.drawLine(number(a, 0), number(a, 1), number(a, 2), number(a, 3), paint)
        paint.pathEffect = null
    }

    private fun drawText(canvas: Canvas, text: String, x: Float, y: Float, size: Float, color: String, align: Int, weight: Int) {
        fill(color)
        paint.textSize = size
        paint.textAlign = when (align) {
            2 -> Paint.Align.CENTER
            3 -> Paint.Align.RIGHT
            else -> Paint.Align.LEFT
        }
        paint.typeface = typeface(weight)
        canvas.drawText(text, x, y, paint)
        paint.textAlign = Paint.Align.LEFT
        paint.typeface = Typeface.DEFAULT
    }

    private fun typeface(weight: Int): Typeface {
        val numeric = when (weight) {
            2 -> 500
            3 -> 600
            4 -> 700
            else -> 400
        }
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            Typeface.create(Typeface.DEFAULT, numeric, false)
        } else {
            Typeface.create(Typeface.DEFAULT, if (numeric >= 600) Typeface.BOLD else Typeface.NORMAL)
        }
    }

    // -- input ----------------------------------------------------------------

    override fun onTouchEvent(event: MotionEvent): Boolean {
        val kind = when (event.actionMasked) {
            MotionEvent.ACTION_DOWN -> 1L
            MotionEvent.ACTION_MOVE -> 2L
            MotionEvent.ACTION_UP -> 3L
            else -> 4L
        }
        val factor = scale().toDouble()
        emit(
            WireMap.encode(
                mapOf(
                    "event" to WireValue.Integer(kind),
                    "x" to WireValue.Decimal(event.x.toDouble() / factor),
                    "y" to WireValue.Decimal(event.y.toDouble() / factor),
                ),
            ),
        )
        return true
    }

    // -- paint helpers ----------------------------------------------------------

    private fun fill(value: String) {
        paint.style = Paint.Style.FILL
        paint.color = parseColor(value)
        applyShadow()
    }

    private fun stroke(value: String, width: Float) {
        paint.style = Paint.Style.STROKE
        paint.strokeWidth = width.coerceAtLeast(0f)
        paint.color = parseColor(value)
        applyShadow()
    }

    private fun applyShadow() {
        if (shadow.blur > 0f || shadow.dx != 0f || shadow.dy != 0f) {
            paint.setShadowLayer(shadow.blur, shadow.dx, shadow.dy, shadow.color)
        } else {
            paint.clearShadowLayer()
        }
    }

    private fun gradient(x0: Float, y0: Float, x1: Float, y1: Float, start: String, end: String): Shader =
        LinearGradient(x0, y0, x1, y1, parseColor(start), parseColor(end), Shader.TileMode.CLAMP)

    private fun cap(value: Int): Paint.Cap = if (value == 2) Paint.Cap.ROUND else Paint.Cap.BUTT

    private fun join(value: Int): Paint.Join = when (value) {
        2 -> Paint.Join.ROUND
        3 -> Paint.Join.BEVEL
        else -> Paint.Join.MITER
    }

    private fun rect(a: JSONArray, offset: Int): RectF {
        rect.set(
            number(a, offset),
            number(a, offset + 1),
            number(a, offset) + number(a, offset + 2),
            number(a, offset + 1) + number(a, offset + 3),
        )
        return rect
    }

    /** Parses `"x,y x,y …"` into a path. */
    private fun points(spec: String, close: Boolean): Path {
        path.reset()
        var first = true
        for (pair in spec.trim().split(' ')) {
            val parts = pair.split(',')
            if (parts.size != 2) continue
            val x = parts[0].toFloatOrNull() ?: continue
            val y = parts[1].toFloatOrNull() ?: continue
            if (first) path.moveTo(x, y) else path.lineTo(x, y)
            first = false
        }
        if (close) path.close()
        return path
    }

    /** Parses the compact `M L C Q Z` spec with absolute coordinates. */
    private fun parsePath(spec: String): Path {
        path.reset()
        val tokens = spec.replace(Regex("([MLCQZ])"), " $1 ").trim().split(Regex("\\s+"))
        var index = 0
        fun next(): Float = tokens.getOrNull(index++)?.toFloatOrNull() ?: 0f
        while (index < tokens.size) {
            when (tokens[index++]) {
                "M" -> path.moveTo(next(), next())
                "L" -> path.lineTo(next(), next())
                "C" -> path.cubicTo(next(), next(), next(), next(), next(), next())
                "Q" -> path.quadTo(next(), next(), next(), next())
                "Z" -> path.close()
                else -> return path
            }
        }
        return path
    }

    private fun number(arguments: JSONArray, index: Int): Float = arguments.optDouble(index).toFloat()

    private fun text(arguments: JSONArray, index: Int): String = arguments.optString(index)

    private companion object {
        /** Parses `#rgb`, `#rrggbb` or `#rrggbbaa` (CSS RGBA order) into ARGB; anything else is transparent. */
        fun parseColor(value: String): Int {
            val hex = value.trim().removePrefix("#")
            if (!hex.all { it in '0'..'9' || it in 'a'..'f' || it in 'A'..'F' }) return Color.TRANSPARENT
            val expanded = when (hex.length) {
                3 -> buildString { for (c in hex) { append(c); append(c) } } + "ff"
                6 -> hex + "ff"
                8 -> hex
                else -> return Color.TRANSPARENT
            }
            val rgba = expanded.toLongOrNull(16) ?: return Color.TRANSPARENT
            val r = ((rgba shr 24) and 0xff).toInt()
            val g = ((rgba shr 16) and 0xff).toInt()
            val b = ((rgba shr 8) and 0xff).toInt()
            val alpha = (rgba and 0xff).toInt()
            return Color.argb(alpha, r, g, b)
        }
    }
}
