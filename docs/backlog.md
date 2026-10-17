# Backlog de mejoras osTicket

Última actualización: 17/10/2026 · Versión editable: [Claude Docs](https://claude.ai/code/artifact/d7a25b4a-76c9-4be4-b1f8-721e3a4ecd61)

Quedan 15 mejoras pendientes: 4 de prioridad alta, 7 media y 4 baja. Nueve tienen algo por decidir antes de implementarse (vea la sección siguiente). La prioridad es una propuesta inicial; al avanzar, actualice prioridad y estado en esta tabla.

## Backlog

| # | Mejora | Área | Prioridad | Estado | Pendiente de decidir |
| --- | --- | --- | --- | --- | --- |
| 1 | Etapa de reapertura por flujo: si el cliente responde a un ticket cerrado, vuelve a una etapa configurada (hoy queda trabado en la etapa final) | Estado de tickets con flujo | Alta | Pendiente | Decisión 3 |
| 2 | Bloquear el cambio manual de estado (cerrar, reabrir, acciones masivas, respuestas predefinidas, filtros) en tickets con flujo | Estado de tickets con flujo | Alta | Pendiente | Decisión 1 |
| 3 | Botón «Derive» en la barra de acciones del ticket, junto a Transferir y Asignar | Flujos | Alta | Pendiente | — |
| 4 | Volver a cerrar el ticket #522647 y corregir la prueba que reabre todos los tickets | Calidad | Alta | Pendiente | — |
| 5 | Excepción para administradores al bloqueo de estado, registrada en la auditoría | Estado de tickets con flujo | Media | Pendiente | Decisión 4 |
| 6 | Estado visible propio por etapa (por ejemplo «Esperando al cliente») | Estado de tickets con flujo | Media | Pendiente | Decisión 2 |
| 7 | Permitir derivar también a administradores y gerentes de departamento | Flujos | Media | Pendiente | Quiénes exactamente |
| 8 | Incorporar tickets existentes a un flujo (creados antes del flujo o con otro tema) | Flujos | Media | Pendiente | Uno a uno o masivo desde la cola |
| 9 | Tope de tickets abiertos por agente en el reparto, para que el primero en volver de vacaciones no reciba toda la cola | Equipos | Media | Pendiente | Tope por equipo o global |
| 10 | Limpieza automática del registro de auditoría | Auditoría | Media | Pendiente | Meses de retención |
| 11 | Guardar las pruebas automáticas en el repositorio (hoy están fuera) y actualizar la contraseña de mgomez que usan | Calidad | Media | Pendiente | — |
| 12 | Reparto automático también para tareas (tasks), no solo tickets | Equipos | Baja | Pendiente | — |
| 13 | Visibilidad del dashboard del portal: hoy público; opción de mostrarlo solo a clientes con sesión o solo a agentes | Dashboard | Baja | Pendiente | Quién debe verlo |
| 14 | Traducir al español los textos nuevos de la interfaz (hoy en inglés, como el resto de osTicket) | General | Baja | Pendiente | — |
| 15 | Medir y mejorar el rendimiento de la búsqueda por contenido con muchos tickets | Búsqueda | Baja | Pendiente | — |

## Decisiones pendientes

Las cuatro primeras definen cómo convive el estado del ticket con el flujo; resolverlas desbloquea las mejoras 1, 2, 5 y 6. La decisión 3 es la más urgente: sin ella, un ticket reabierto por el cliente queda trabado en la etapa final.

| Decisión | Pregunta | Recomendación | Mejora |
| --- | --- | --- | --- |
| 1 | ¿El bloqueo del cambio manual de estado aplica a todos los flujos o solo a los Fixed? | Solo a los Fixed; los Free conservan el control manual | 2 |
| 2 | ¿Cada etapa puede tener su propio estado visible, o basta con abierto en las intermedias y cerrado en la final? | Opcional por etapa; sin configurar, abierto/cerrado | 6 |
| 3 | Cuando el cliente responde a un ticket cerrado, ¿a qué etapa vuelve? | A una «etapa de reapertura» configurada por flujo; si no hay, a la inicial | 1 |
| 4 | ¿Los administradores pueden saltarse el bloqueo? | Sí, solo administradores, y queda en la auditoría | 5 |
| — | ¿Quién más puede derivar además del agente asignado y el líder del equipo? | Administradores y gerentes del departamento de la etapa | 7 |
| — | ¿Los tickets existentes se incorporan a un flujo uno a uno o en masa? | Ambos: desde el ticket y como acción masiva en la cola | 8 |
| — | ¿El tope de tickets abiertos por agente se define por equipo o para todo el helpdesk? | Por equipo, en la ficha del equipo | 9 |
| — | ¿Cuántos meses se conserva el registro de auditoría? | 12 meses, configurable | 10 |
| — | ¿Quién debe ver el dashboard del portal? | Configurable; por defecto, público como hoy | 13 |
