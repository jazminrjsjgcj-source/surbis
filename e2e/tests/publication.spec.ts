import { test } from '@playwright/test'

/**
 * Publicación mínima de una encuesta.
 *
 * PENDIENTE: esta pantalla todavia no existe. Llega en la Fase 4.
 * Requisitos: RF-AO-PUB-005, 006 y 007
 *
 * El archivo existe vacio a proposito. Se acordo cubrir cuatro flujos con
 * pruebas de navegador y solo uno —el acceso— tiene pantalla hoy. Dejarlo
 * anotado evita dos cosas: que se olvide, y que el cierre de esta tarea se
 * lea como "Playwright cubre los cuatro flujos" cuando cubre uno.
 *
 * No se escriben las pruebas ahora porque una prueba contra una pantalla que
 * no existe no puede pasar nunca, y una suite que no puede pasar acaba
 * desactivada entera.
 */
test.fixme('Publicación mínima de una encuesta — llega en la Fase 4', async () => {
    // Sin cuerpo. test.fixme lo marca como pendiente y lo lista en cada
    // ejecucion sin hacerla fallar.
})
