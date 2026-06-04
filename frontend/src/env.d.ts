/// <reference types="vite/client" />

declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<{}, {}, any>
  export default component
}

declare module '*.vue?raw' {
  const content: string
  export default content
}

// ECharts module declarations
declare module 'vue-echarts' {
  import type { DefineComponent } from 'vue'
  const VChart: DefineComponent<Record<string, any>, Record<string, any>, any>
  export default VChart
}

declare module 'echarts/core' {
  export * from 'echarts'
}

declare module 'echarts/renderers' {
  export const CanvasRenderer: any
  export const SVGRenderer: any
}

declare module 'echarts/charts' {
  export const LineChart: any
  export const PieChart: any
  export const BarChart: any
  export const ScatterChart: any
  export const HeatmapChart: any
}

declare module 'echarts/components' {
  export const GridComponent: any
  export const TooltipComponent: any
  export const LegendComponent: any
  export const TitleComponent: any
  export const ToolboxComponent: any
  export const DataZoomComponent: any
  export const VisualMapComponent: any
}

// QRCode module
declare module 'qrcode.vue' {
  import type { DefineComponent } from 'vue'
  const QrcodeVue: DefineComponent<
    { value: string; size?: number; level?: string; renderAs?: string; background?: string; foreground?: string },
    Record<string, any>,
    any
  >
  export default QrcodeVue
}

// HTML5 QR scanner
declare module 'html5-qrcode' {
  export class Html5QrcodeScanner {
    constructor(elementId: string, config: Record<string, any>, verbose?: boolean)
    render(
      onScanSuccess: (decodedText: string, decodedResult?: any) => void,
      onScanFailure?: (errorMessage: string) => void
    ): void
    clear(): Promise<void>
    pause(): void
    resume(): void
  }
}

// vuedraggable
declare module 'vuedraggable' {
  import type { DefineComponent } from 'vue'
  const draggable: DefineComponent<Record<string, any>, Record<string, any>, any>
  export default draggable
}

// Cloudflare Turnstile
interface Window {
  turnstile?: {
    render: (container: string | HTMLElement, options: Record<string, any>) => string
    reset: (widgetId: string) => void
    remove: (widgetId: string) => void
    getResponse: (widgetId: string) => string
  }
}
