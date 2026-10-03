// Contiene las variables correspondientes a los colores que seran empleados en el sistema

export interface Colors {
    azulOscuro: string;
    azulClaro: string;
    naranjaClaro: string;
    naranjaSuave: string;
    papel: string;
    grisPizarra: string;
    grisMedio: string;
    grisClaro: string;
    verdeClaro: string;
    amarilloClaro: string;
}

// Exportamos un objeto con todos los colores que utilizaremos en nuestra aplicación.
export const colors: Colors = {
    azulOscuro: '#0F2A47',
    azulClaro: '#2563EB',
    naranjaClaro: '#F97316',
    naranjaSuave: '#FDBA74', // texto de la insignia sobre azul oscuro
    papel: '#FAF8F3', // fondo de la guía de envío
    grisPizarra: '#475569', // texto secundario sobre fondo claro
    grisMedio: '#64748B', // detalles y bordes
    grisClaro: '#CBD5E1', // texto secundario sobre fondo oscuro
    verdeClaro: '#4ADE80', // estado: entregado / activo
    amarilloClaro: '#FACC15', // estado: en ruta
};
