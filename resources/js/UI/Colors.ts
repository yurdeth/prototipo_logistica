// resources/js/UI/Colors.ts
// Contiene las variables correspondientes a los colores que serán empleados en el sistema

export interface Colors {
    // Marca
    azulOscuro: string;
    azulClaro: string;
    naranjaClaro: string;
    naranjaSuave: string;
    // Neutros
    papel: string;
    grisPizarra: string;
    grisMedio: string;
    grisClaro: string;
    // Estados
    verde: string;
    verdeClaro: string;
    amarillo: string;
    amarilloClaro: string;
    rojo: string;
    cian: string;
}

// Exportamos un objeto con todos los colores que utilizaremos en nuestra aplicación.
export const colors: Colors = {
    // Marca
    azulOscuro: '#0F2A47',
    azulClaro: '#2563EB',
    naranjaClaro: '#F97316',
    naranjaSuave: '#FDBA74', // texto de la insignia sobre azul oscuro

    // Neutros
    papel: '#FAF8F3', // fondo de la guía de envío (login)
    grisPizarra: '#475569', // texto secundario sobre fondo claro
    grisMedio: '#64748B', // detalles y bordes
    grisClaro: '#CBD5E1', // texto secundario sobre fondo oscuro

    // Estados
    verde: '#16A34A', // entregado, a tiempo
    verdeClaro: '#4ADE80', // igual, para fondos oscuros
    amarillo: '#CA8A04', // en ruta
    amarilloClaro: '#FACC15', // igual, para fondos oscuros
    rojo: '#DC2626', // retraso, incidencias
    cian: '#0891B2', // pendiente, información
};
