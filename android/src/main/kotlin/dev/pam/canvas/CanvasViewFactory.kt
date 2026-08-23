package dev.pam.canvas
import android.content.Context
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.RectF
import android.view.MotionEvent
import android.view.View
import dev.pam.nativeapp.protocol.WireMap
import dev.pam.nativeapp.protocol.WireValue
import dev.pam.nativeapp.views.NativeViewFactory
import org.json.JSONArray

class CanvasViewFactory(@Suppress("UNUSED_PARAMETER") context:Context):NativeViewFactory{
 override fun create(context:Context,emit:(ByteArray)->Unit):View=PamCanvas(context,emit)
 override fun update(view:View,properties:Map<String,WireValue>)=(view as PamCanvas).update(properties)
 override fun release(view:View){}
}
private class PamCanvas(context:Context,private val emit:(ByteArray)->Unit):View(context){
 private var commands=JSONArray();private var revision=-1L;private val paint=Paint(Paint.ANTI_ALIAS_FLAG)
 fun update(values:Map<String,WireValue>){val next=(values["revision"]as?WireValue.Integer)?.value?:0;if(next==revision)return;val json=(values["displayList"]as?WireValue.Text)?.value?:"[]";commands=runCatching{JSONArray(json)}.getOrDefault(JSONArray());revision=next;invalidate()}
 override fun onDraw(canvas:Canvas){super.onDraw(canvas);for(i in 0 until commands.length()){val command=commands.optJSONObject(i)?:continue;val kind=command.optInt("k");val a=command.optJSONArray("a")?:JSONArray();runCatching{when(kind){1->canvas.save();2->if(canvas.saveCount>1)canvas.restore();3->canvas.translate(f(a,0),f(a,1));4->canvas.rotate(f(a,0));5->canvas.scale(f(a,0),f(a,1));6->canvas.clipRect(f(a,0),f(a,1),f(a,0)+f(a,2),f(a,1)+f(a,3));7->canvas.drawColor(color(s(a,0)));8->{fill(s(a,4));canvas.drawRect(f(a,0),f(a,1),f(a,0)+f(a,2),f(a,1)+f(a,3),paint)};9->{stroke(s(a,4),f(a,5));canvas.drawRect(f(a,0),f(a,1),f(a,0)+f(a,2),f(a,1)+f(a,3),paint)};10->{fill(s(a,3));canvas.drawCircle(f(a,0),f(a,1),f(a,2),paint)};11->{stroke(s(a,4),f(a,5));canvas.drawLine(f(a,0),f(a,1),f(a,2),f(a,3),paint)};12->{fill(s(a,4));paint.textSize=f(a,3);canvas.drawText(s(a,0),f(a,1),f(a,2),paint)}}}}
 override fun onTouchEvent(event:MotionEvent):Boolean{val kind=when(event.actionMasked){MotionEvent.ACTION_DOWN->1L;MotionEvent.ACTION_MOVE->2L;MotionEvent.ACTION_UP->3L;else->4L};emit(WireMap.encode(mapOf("event" to WireValue.Integer(kind),"x" to WireValue.Decimal(event.x.toDouble()),"y" to WireValue.Decimal(event.y.toDouble()))));return true}
 private fun fill(value:String){paint.style=Paint.Style.FILL;paint.color=color(value)};private fun stroke(value:String,width:Float){paint.style=Paint.Style.STROKE;paint.strokeWidth=width.coerceAtLeast(0f);paint.color=color(value)}
 private fun color(value:String)=runCatching{Color.parseColor(value)}.getOrDefault(Color.TRANSPARENT);private fun f(a:JSONArray,i:Int)=a.optDouble(i).toFloat();private fun s(a:JSONArray,i:Int)=a.optString(i)
}
