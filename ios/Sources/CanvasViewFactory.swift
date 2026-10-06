import CoreGraphics
import Foundation
import PamNative
import UIKit

public final class CanvasViewFactory: NativeViewFactory, @unchecked Sendable {
    public init() {}

    public func create(context: AnyObject?, emit: @escaping (Data) -> Void) -> UIView {
        PamCanvasView(emit: emit)
    }

    public func update(view: UIView, properties: [String: WireValue]) {
        (view as? PamCanvasView)?.update(properties)
    }

    public func release(view: UIView) {}
}

/// Replays the JSON display list `[{"k":kind,"a":[...]}]` with Core Graphics.
///
/// Kinds 1–12 are the 0.1.0 contract, 13–25 were added in 0.2.0. iOS draws in
/// points, so the `density` host property needs no scaling here; pointer
/// coordinates are already in the units the scene uses.
private final class PamCanvasView: UIView, @unchecked Sendable {
    private let emit: (Data) -> Void
    private var commands: [[String: Any]] = []
    private var revision: Int64 = -1
    private var layerStack: [Int] = []

    init(emit: @escaping (Data) -> Void) {
        self.emit = emit
        super.init(frame: .zero)
        isMultipleTouchEnabled = false
        contentMode = .redraw
        backgroundColor = .clear
    }

    required init?(coder: NSCoder) {
        nil
    }

    func update(_ values: [String: WireValue]) {
        guard case let .integer(next)? = values["revision"], next != revision,
              case let .text(json)? = values["displayList"],
              let data = json.data(using: .utf8),
              let decoded = try? JSONSerialization.jsonObject(with: data) as? [[String: Any]]
        else { return }
        revision = next
        commands = decoded
        setNeedsDisplay()
    }

    override func draw(_ rect: CGRect) {
        guard let context = UIGraphicsGetCurrentContext() else { return }
        context.saveGState()
        layerStack.removeAll()
        for command in commands {
            guard let kind = command["k"] as? Int, let a = command["a"] as? [Any] else { continue }
            draw(kind, a, in: context)
        }
        while !layerStack.isEmpty {
            restore(context)
        }
        context.restoreGState()
    }

    private func draw(_ kind: Int, _ a: [Any], in context: CGContext) {
        switch kind {
        case 1:
            context.saveGState()
            layerStack.append(0)
        case 2:
            restore(context)
        case 3:
            context.translateBy(x: n(a, 0), y: n(a, 1))
        case 4:
            context.rotate(by: n(a, 0) * .pi / 180)
        case 5:
            context.scaleBy(x: n(a, 0), y: n(a, 1))
        case 6:
            context.clip(to: rect(a, 0))
        case 7:
            context.setFillColor(color(s(a, 0)).cgColor)
            context.fill(bounds)
        case 8:
            fill(context, s(a, 4))
            context.fill(rect(a, 0))
        case 9:
            stroke(context, s(a, 4), n(a, 5))
            context.stroke(rect(a, 0))
        case 10:
            fill(context, s(a, 3))
            let radius = n(a, 2)
            context.fillEllipse(in: CGRect(x: n(a, 0) - radius, y: n(a, 1) - radius, width: radius * 2, height: radius * 2))
        case 11:
            stroke(context, s(a, 4), n(a, 5))
            context.move(to: CGPoint(x: n(a, 0), y: n(a, 1)))
            context.addLine(to: CGPoint(x: n(a, 2), y: n(a, 3)))
            context.strokePath()
        case 12:
            let attributes: [NSAttributedString.Key: Any] = [
                .font: UIFont.systemFont(ofSize: n(a, 3)),
                .foregroundColor: color(s(a, 4)),
            ]
            (s(a, 0) as NSString).draw(at: CGPoint(x: n(a, 1), y: n(a, 2) - n(a, 3)), withAttributes: attributes)
        case 13:
            fill(context, s(a, 5))
            context.addPath(roundRect(rect(a, 0), n(a, 4)))
            context.fillPath()
        case 14:
            stroke(context, s(a, 5), n(a, 6))
            context.addPath(roundRect(rect(a, 0), n(a, 4)))
            context.strokePath()
        case 15:
            drawArc(context, a)
        case 16:
            drawSector(context, a)
        case 17:
            drawPolyline(context, a)
        case 18:
            fill(context, s(a, 1))
            context.addPath(points(s(a, 0), close: true))
            context.fillPath()
        case 19:
            drawPath(context, a)
        case 20:
            fillGradient(context, roundRect(rect(a, 0), n(a, 4)), n(a, 5), n(a, 6), n(a, 7), n(a, 8), s(a, 9), s(a, 10))
        case 21:
            fillGradient(context, parsePath(s(a, 0)), n(a, 1), n(a, 2), n(a, 3), n(a, 4), s(a, 5), s(a, 6))
        case 22:
            alpha(context, n(a, 0))
        case 23:
            drawLabel(context, a)
        case 24:
            context.setShadow(offset: CGSize(width: n(a, 2), height: n(a, 3)), blur: max(0, n(a, 1)), color: color(s(a, 0)).cgColor)
        case 25:
            drawDashedLine(context, a)
        default:
            break
        }
    }

    // MARK: - State

    private func restore(_ context: CGContext) {
        guard let layers = layerStack.popLast() else { return }
        var remaining = layers
        while remaining > 0 {
            context.endTransparencyLayer()
            remaining -= 1
        }
        context.restoreGState()
    }

    private func alpha(_ context: CGContext, _ opacity: CGFloat) {
        guard !layerStack.isEmpty else { return }
        context.setAlpha(min(1, max(0, opacity)))
        context.beginTransparencyLayer(auxiliaryInfo: nil)
        layerStack[layerStack.count - 1] += 1
    }

    // MARK: - Shapes

    private func drawArc(_ context: CGContext, _ a: [Any]) {
        let center = CGPoint(x: n(a, 0), y: n(a, 1))
        let start = (n(a, 3) - 90) * .pi / 180
        let sweep = n(a, 4) * .pi / 180
        let arc = UIBezierPath(arcCenter: center, radius: n(a, 2), startAngle: start, endAngle: start + sweep, clockwise: sweep >= 0)
        stroke(context, s(a, 5), n(a, 6))
        context.setLineCap(cap(i(a, 7)))
        context.addPath(arc.cgPath)
        context.strokePath()
        context.setLineCap(.butt)
    }

    private func drawSector(_ context: CGContext, _ a: [Any]) {
        let center = CGPoint(x: n(a, 0), y: n(a, 1))
        let outer = n(a, 2)
        let inner = min(max(0, n(a, 3)), outer)
        let start = (n(a, 4) - 90) * .pi / 180
        let sweep = n(a, 5) * .pi / 180
        let sector = UIBezierPath()
        if inner <= 0 {
            sector.move(to: center)
            sector.addArc(withCenter: center, radius: outer, startAngle: start, endAngle: start + sweep, clockwise: sweep >= 0)
        } else {
            sector.addArc(withCenter: center, radius: outer, startAngle: start, endAngle: start + sweep, clockwise: sweep >= 0)
            sector.addArc(withCenter: center, radius: inner, startAngle: start + sweep, endAngle: start, clockwise: sweep < 0)
        }
        sector.close()
        fill(context, s(a, 6))
        context.addPath(sector.cgPath)
        context.fillPath()
    }

    private func drawPolyline(_ context: CGContext, _ a: [Any]) {
        stroke(context, s(a, 1), n(a, 2))
        context.setLineCap(cap(i(a, 3)))
        context.setLineJoin(join(i(a, 4)))
        context.addPath(points(s(a, 0), close: false))
        context.strokePath()
        context.setLineCap(.butt)
        context.setLineJoin(.miter)
    }

    private func drawPath(_ context: CGContext, _ a: [Any]) {
        let shape = parsePath(s(a, 0))
        if i(a, 3) == 2 {
            stroke(context, s(a, 1), n(a, 2))
            context.setLineCap(.round)
            context.setLineJoin(.round)
            context.addPath(shape)
            context.strokePath()
            context.setLineCap(.butt)
            context.setLineJoin(.miter)
        } else {
            fill(context, s(a, 1))
            context.addPath(shape)
            context.fillPath()
        }
    }

    private func fillGradient(_ context: CGContext, _ shape: CGPath, _ x0: CGFloat, _ y0: CGFloat, _ x1: CGFloat, _ y1: CGFloat, _ start: String, _ end: String) {
        let colors = [color(start).cgColor, color(end).cgColor] as CFArray
        guard let gradient = CGGradient(colorsSpace: CGColorSpaceCreateDeviceRGB(), colors: colors, locations: [0, 1]) else { return }
        context.saveGState()
        context.addPath(shape)
        context.clip()
        context.drawLinearGradient(gradient, start: CGPoint(x: x0, y: y0), end: CGPoint(x: x1, y: y1), options: [.drawsBeforeStartLocation, .drawsAfterEndLocation])
        context.restoreGState()
    }

    private func drawDashedLine(_ context: CGContext, _ a: [Any]) {
        stroke(context, s(a, 4), n(a, 5))
        context.setLineDash(phase: 0, lengths: [max(0.1, n(a, 6)), max(0.1, n(a, 7))])
        context.move(to: CGPoint(x: n(a, 0), y: n(a, 1)))
        context.addLine(to: CGPoint(x: n(a, 2), y: n(a, 3)))
        context.strokePath()
        context.setLineDash(phase: 0, lengths: [])
    }

    private func drawLabel(_ context: CGContext, _ a: [Any]) {
        let font = UIFont.systemFont(ofSize: n(a, 3), weight: weight(i(a, 6)))
        let attributes: [NSAttributedString.Key: Any] = [.font: font, .foregroundColor: color(s(a, 4))]
        let text = s(a, 0) as NSString
        let width = text.size(withAttributes: attributes).width
        var x = n(a, 1)
        switch i(a, 5) {
        case 2: x -= width / 2
        case 3: x -= width
        default: break
        }
        text.draw(at: CGPoint(x: x, y: n(a, 2) - font.ascender), withAttributes: attributes)
    }

    // MARK: - Input

    override func touchesBegan(_ touches: Set<UITouch>, with event: UIEvent?) { send(1, touches) }
    override func touchesMoved(_ touches: Set<UITouch>, with event: UIEvent?) { send(2, touches) }
    override func touchesEnded(_ touches: Set<UITouch>, with event: UIEvent?) { send(3, touches) }
    override func touchesCancelled(_ touches: Set<UITouch>, with event: UIEvent?) { send(4, touches) }

    private func send(_ kind: Int64, _ touches: Set<UITouch>) {
        guard let point = touches.first?.location(in: self) else { return }
        let payload: [String: WireValue] = [
            "event": .integer(kind),
            "x": .decimal(Double(point.x)),
            "y": .decimal(Double(point.y)),
        ]
        guard let data = try? WireMap.encode(payload) else { return }
        emit(data)
    }

    // MARK: - Helpers

    private func fill(_ context: CGContext, _ value: String) {
        context.setFillColor(color(value).cgColor)
    }

    private func stroke(_ context: CGContext, _ value: String, _ width: CGFloat) {
        context.setStrokeColor(color(value).cgColor)
        context.setLineWidth(max(0, width))
    }

    private func roundRect(_ rect: CGRect, _ radius: CGFloat) -> CGPath {
        UIBezierPath(roundedRect: rect, cornerRadius: max(0, radius)).cgPath
    }

    private func cap(_ value: Int) -> CGLineCap {
        value == 2 ? .round : .butt
    }

    private func join(_ value: Int) -> CGLineJoin {
        switch value {
        case 2: return .round
        case 3: return .bevel
        default: return .miter
        }
    }

    private func weight(_ value: Int) -> UIFont.Weight {
        switch value {
        case 2: return .medium
        case 3: return .semibold
        case 4: return .bold
        default: return .regular
        }
    }

    private func rect(_ a: [Any], _ offset: Int) -> CGRect {
        CGRect(x: n(a, offset), y: n(a, offset + 1), width: n(a, offset + 2), height: n(a, offset + 3))
    }

    /// Parses `"x,y x,y …"` into a path.
    private func points(_ spec: String, close: Bool) -> CGPath {
        let path = CGMutablePath()
        var first = true
        for pair in spec.split(separator: " ") {
            let parts = pair.split(separator: ",")
            guard parts.count == 2, let x = Double(parts[0]), let y = Double(parts[1]) else { continue }
            let point = CGPoint(x: x, y: y)
            if first {
                path.move(to: point)
            } else {
                path.addLine(to: point)
            }
            first = false
        }
        if close {
            path.closeSubpath()
        }
        return path
    }

    /// Parses the compact `M L C Q Z` spec with absolute coordinates.
    private func parsePath(_ spec: String) -> CGPath {
        let path = CGMutablePath()
        var spaced = ""
        for character in spec {
            if "MLCQZ".contains(character) {
                spaced += " \(character) "
            } else {
                spaced.append(character)
            }
        }
        let tokens = spaced.split(whereSeparator: { $0 == " " || $0 == "\n" || $0 == "\t" }).map(String.init)
        var index = 0
        func next() -> CGFloat {
            defer { index += 1 }
            return index < tokens.count ? CGFloat(Double(tokens[index]) ?? 0) : 0
        }
        while index < tokens.count {
            let verb = tokens[index]
            index += 1
            switch verb {
            case "M":
                let x = next()
                let y = next()
                path.move(to: CGPoint(x: x, y: y))
            case "L":
                let x = next()
                let y = next()
                path.addLine(to: CGPoint(x: x, y: y))
            case "C":
                let x1 = next()
                let y1 = next()
                let x2 = next()
                let y2 = next()
                let x = next()
                let y = next()
                path.addCurve(to: CGPoint(x: x, y: y), control1: CGPoint(x: x1, y: y1), control2: CGPoint(x: x2, y: y2))
            case "Q":
                let cx = next()
                let cy = next()
                let x = next()
                let y = next()
                path.addQuadCurve(to: CGPoint(x: x, y: y), control: CGPoint(x: cx, y: cy))
            case "Z":
                path.closeSubpath()
            default:
                return path
            }
        }
        return path
    }

    private func n(_ a: [Any], _ index: Int) -> CGFloat {
        guard a.indices.contains(index), let number = a[index] as? NSNumber else { return 0 }
        return CGFloat(number.doubleValue)
    }

    private func i(_ a: [Any], _ index: Int) -> Int {
        guard a.indices.contains(index), let number = a[index] as? NSNumber else { return 0 }
        return number.intValue
    }

    private func s(_ a: [Any], _ index: Int) -> String {
        guard a.indices.contains(index), let text = a[index] as? String else { return "" }
        return text
    }

    /// Parses `#rgb`, `#rrggbb` or `#rrggbbaa` (CSS RGBA order); anything else is clear.
    private func color(_ hex: String) -> UIColor {
        var value = hex.trimmingCharacters(in: .whitespacesAndNewlines)
        if value.hasPrefix("#") {
            value.removeFirst()
        }
        if value.count == 3 {
            value = value.map { "\($0)\($0)" }.joined()
        }
        guard value.count == 6 || value.count == 8, var number = UInt64(value, radix: 16) else { return .clear }
        if value.count == 6 {
            number = (number << 8) | 255
        }
        return UIColor(
            red: CGFloat((number >> 24) & 255) / 255,
            green: CGFloat((number >> 16) & 255) / 255,
            blue: CGFloat((number >> 8) & 255) / 255,
            alpha: CGFloat(number & 255) / 255
        )
    }
}
