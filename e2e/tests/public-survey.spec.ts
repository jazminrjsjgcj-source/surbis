import { test } from '@playwright/test'

/**
 * Encuesta pública.
 *
 * PENDIENTE: esta pantalla todavia no existe. Llega en la Fase 13.
 * Requisitos: RF-ENC-001 a 016
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
test.fixme('Encuesta pública — llega en la Fase 13', async () => {
    // Sin cuerpo. test.fixme lo marca como pendiente y lo lista en cada
    // ejecucion sin hacerla fallar.
})
