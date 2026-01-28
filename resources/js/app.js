// resources/js/app.js
import './bootstrap'
import Alpine from 'alpinejs'

window.Alpine = Alpine

// ✅ Store global para controlar el modal (Salidas)
Alpine.store('salidas', {
    show: false,

    // items del modal (lo que se manda al controller)
    items: [],

    open() {
        this.show = true
    },

    close() {
        this.show = false
        this.items = []
    },

    addItem(item) {
        // item debe ser objeto { inventario_id, descripcion, unidad, cantidad, devolvible, nivel, departamento }
        this.items.push(item)
    },

    removeItem(index) {
        this.items.splice(index, 1)
    },
})

Alpine.start()
