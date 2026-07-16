// Shim de tipos para vuedraggable (Vue 3)
// Este archivo NO tiene top-level imports/exports para que TypeScript lo
// trate como un script global ambient y REEMPLACE la declaración del paquete.
// El paquete no declara SlotsType, por lo que Volar infería slots como {}.

declare module 'vuedraggable' {
  import type {
    DefineComponent,
    SlotsType,
    VNodeProps,
    AllowedComponentProps,
    ComponentCustomProps,
    ComponentOptionsMixin,
  } from 'vue'

  const draggableComponent: DefineComponent<
    // Props
    {
      list: { type: ArrayConstructor; required: false; default: null }
      modelValue: { type: ArrayConstructor; required: false; default: null }
      itemKey: { type: (FunctionConstructor | StringConstructor)[]; required: false }
      clone: { type: FunctionConstructor; default: (original: any) => any }
      tag: { type: StringConstructor; default: string }
      move: { type: FunctionConstructor; default: null }
      componentData: { type: ObjectConstructor; required: false; default: null }
      group: { type: (StringConstructor | ObjectConstructor)[]; required: false }
      ghostClass: { type: StringConstructor; required: false }
      dragClass: { type: StringConstructor; required: false }
      [key: string]: any
    },
    // RawBindings
    unknown,
    // Data
    { error: boolean },
    // Computed
    Record<string, any>,
    // Methods
    Record<string, any>,
    ComponentOptionsMixin,
    ComponentOptionsMixin,
    any[],
    any,
    VNodeProps & AllowedComponentProps & ComponentCustomProps,
    Readonly<Record<string, any>>,
    Record<string, any>,
    // Slots — THIS is what was missing
    SlotsType<{
      item: { element: any; index: number }
      header: Record<string, never>
      footer: Record<string, never>
    }>
  >

  export default draggableComponent
}
