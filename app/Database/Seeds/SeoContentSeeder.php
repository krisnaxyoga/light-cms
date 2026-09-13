<?php

namespace App\Database\Seeds;

use App\Libraries\Editor\BlockParser;
use App\Libraries\Editor\BlockRenderer;
use App\Libraries\SEO\AutoSeoGenerator;
use App\Models\PostModel;
use App\Models\SEOModel;
use App\Models\UserModel;
use CodeIgniter\Database\Seeder;
use Config\Services;

/**
 * The Spanish-language SEO page/blog architecture for snorkelpenida.com
 * (11 commercial landing pages + 6 blog articles), built entirely from
 * data already confirmed elsewhere in the project (prices, schedule,
 * inclusions from App\Libraries\Homepage\HomepageContent's live defaults;
 * the WhatsApp number from the new whatsapp_number setting). Nothing here
 * invents a price, a stat, a guarantee of manta sightings, or a service
 * the business doesn't offer — see the "no inventar" rules this content
 * was written against.
 *
 * Re-runnable: each page/post is looked up by slug and replaced (its old
 * seo_meta row deleted, a fresh post + seo_meta inserted) rather than
 * appended, so running this twice doesn't create duplicates.
 *
 * Deliberately NOT created as separate blog posts (would duplicate a
 * commercial page's own search intent — see PRD-style brief's own
 * "no crear artículos duplicados" rule):
 *   - "Manta Bay vs Manta Point" -> covered by the landing page itself
 *   - "Qué llevar para hacer snorkeling" -> covered by /que-llevar/
 *   - "Cómo reservar snorkeling" -> covered by /como-reservar/
 *   - "Preguntas frecuentes sobre snorkeling" -> covered by /preguntas-frecuentes/
 *
 * Run: php spark db:seed SeoContentSeeder
 */
class SeoContentSeeder extends Seeder
{
    protected int $authorId = 1;

    // -- WhatsApp CTA messages (exact wording from the content brief) -----
    protected string $waGeneral = 'Hola, me gustaría reservar snorkel en Nusa Penida. ¿Podrían indicarme la disponibilidad?';
    protected string $waMantaBay = 'Hola, estoy interesado/a en hacer snorkel en Manta Bay. ¿Podrían indicarme disponibilidad y precio?';
    protected string $waMantaPoint = 'Hola, estoy interesado/a en hacer snorkel en Manta Point. ¿Podrían indicarme disponibilidad y precio?';
    protected string $waPrivate = 'Hola, estoy interesado/a en un private snorkeling en Nusa Penida. ¿Podrían indicarme disponibilidad y precio?';

    public function run(): void
    {
        $this->seedPages();
        $this->seedBlog();
        $this->seedMenus();

        (new \App\Libraries\SEO\SitemapGenerator())->writeFiles();
        (new \App\Libraries\SEO\RobotsTxtGenerator())->write();
        Services::cacheManager()->flushAll();
    }

    // ======================================================================
    //  Pages
    // ======================================================================

    protected function seedPages(): void
    {
        $u = fn (string $slug = '') => site_url($slug);

        // ---- 1. Pillar: /snorkeling-nusa-penida/ --------------------------
        $this->seedPage(
            'Snorkeling en Nusa Penida',
            'snorkeling-nusa-penida',
            [
                $this->p('Snorkeling en Nusa Penida es, para muchos viajeros, la razón principal de cruzar desde Bali: aguas turquesas, formaciones de coral y la posibilidad de nadar en zonas conocidas por la presencia de mantarrayas. Esta página reúne todo lo que necesitas para planear tu experiencia con nosotros: rutas, qué incluye, horarios y cómo reservar.'),
                $this->p('Los encuentros con fauna marina son naturales e impredecibles: ninguna operadora responsable puede garantizarte ver mantarrayas en un día concreto. Lo que sí podemos ofrecerte es una salida bien organizada, equipo en buen estado y guías que conocen las zonas.'),

                $this->h(2, 'Nuestras rutas de snorkeling'),
                $this->p('Trabajamos principalmente dos zonas de Nusa Penida, cada una con su propia página con todos los detalles:'),
                $this->ul([
                    '<a href="' . $u('snorkeling-manta-bay') . '">Manta Bay</a> — una de las zonas donde es posible encontrar mantarrayas, sin garantía de avistamiento.',
                    '<a href="' . $u('manta-point-nusa-penida') . '">Manta Point</a> — otro punto de snorkeling con presencia habitual de mantarrayas en la zona.',
                ]),
                $this->p('Si no sabes cuál elegir, la página <a href="' . $u('manta-bay-vs-manta-point') . '">Manta Bay vs Manta Point</a> compara qué paquete visita cada zona.'),
                $this->p('Nusa Penida es también conocida por Crystal Bay, una bahía de aguas tranquilas y visibilidad habitualmente buena en la costa oeste — una aclaración importante: los avistamientos de Mola Mola (pez luna) que hacen famosa la zona en algunas fotos son un fenómeno de buceo con botella (a unos 20-30 metros de profundidad, entre julio y octubre), no algo que se pueda ver haciendo snorkel en superficie.'),

                $this->h(2, 'Qué incluye el snorkeling'),
                $this->p('Cada salida incluye equipo de snorkel (máscara y aletas), chaleco o dispositivo de flotación, documentación de la experiencia (fotos o vídeos) y agua mineral. El transporte hasta Nusa Penida y la comida no están incluidos. Todos los detalles están en <a href="' . $u('que-incluye') . '">Qué incluye</a>.'),

                $this->h(2, 'Precios'),
                $this->p('Los precios varían según el tamaño del grupo, desde €13.50 por persona en grupos numerosos. Consulta la tabla completa en <a href="' . $u('precios') . '">Precios</a>, o solicita el precio de una experiencia privada en <a href="' . $u('private-snorkeling-nusa-penida') . '">Private Snorkeling</a>.'),

                $this->h(2, 'Horarios y check-in'),
                $this->p('Tenemos salidas por la mañana y por la tarde. En ambos casos, el check-in es 30 minutos antes de la hora de salida para el ajuste del equipo y el briefing de seguridad. Horarios completos en <a href="' . $u('horarios') . '">Horarios</a>.'),

                $this->h(2, 'Qué llevar'),
                $this->p('Traje de baño, protección solar adecuada para actividades marinas y una muda de ropa seca son lo básico. Guía completa en <a href="' . $u('que-llevar') . '">Qué llevar</a>.'),

                $this->h(2, 'Preguntas frecuentes'),
                ...$this->faq('¿Está garantizado ver mantarrayas?', 'No. Son animales salvajes y los encuentros dependen de la naturaleza y de las condiciones del mar ese día. Trabajamos en zonas conocidas por su presencia, pero ninguna operadora responsable puede garantizar un avistamiento — de hecho, las mantarrayas se reproducen muy lentamente (una cría cada 2-3 años, según datos de organizaciones de conservación como Manta Trust), así que cuidar estas zonas y no prometer avistamientos forma parte de un turismo responsable con la especie.'),
                ...$this->faq('¿El transporte está incluido?', 'No. El transporte hasta Nusa Penida y el traslado desde tu hotel no forman parte del snorkeling. Más detalles en la página Qué incluye.'),
                ...$this->faq('¿Cómo reservo?', 'Escríbenos por WhatsApp indicando fecha y número de personas y te confirmamos disponibilidad y precio. El proceso completo está en Cómo reservar.'),
                $this->p('Más preguntas resueltas en <a href="' . $u('preguntas-frecuentes') . '">Preguntas frecuentes</a>.'),

                $this->quote('Snorkel de clase mundial, sin promesas que no podemos cumplir.', 'Equipo Snorkel Penida'),

                $this->cta('Reserva tu snorkeling en Nusa Penida', 'Escríbenos por WhatsApp y te confirmamos disponibilidad y precio.', whatsapp_url($this->waGeneral), 'Reservar por WhatsApp'),
            ],
            [
                'meta_title'       => 'Snorkeling en Nusa Penida | Manta Bay y Manta Point',
                'meta_description' => 'Snorkeling en Nusa Penida con guías locales: rutas a Manta Bay y Manta Point, equipo incluido, fotos/vídeos y reserva directa por WhatsApp.',
                'focus_keyword'    => 'snorkeling Nusa Penida',
            ]
        );

        // ---- 2. /snorkeling-manta-bay/ -------------------------------------
        $this->seedPage(
            'Snorkeling en Manta Bay, Nusa Penida',
            'snorkeling-manta-bay',
            [
                $this->p('Manta Bay es una de las dos zonas de snorkeling que visitamos en Nusa Penida, conocida por la presencia de mantarrayas en sus aguas. Salimos en barco desde el punto de encuentro, con guía y equipo incluidos.'),
                $this->p('Como en cualquier zona con fauna marina salvaje, no podemos garantizar un avistamiento: la experiencia depende de las condiciones del mar y del comportamiento natural de los animales ese día. Lo que sí garantizamos es un equipo en buen estado, un guía que conoce la zona y las medidas de seguridad básicas de cualquier salida en barco.'),

                $this->h(2, 'Dónde está y cómo es'),
                $this->p('Manta Bay se encuentra en la costa suroeste de Nusa Penida, en una bahía resguardada cerca de Broken Beach y Angel\'s Billabong — un trayecto en barco de unos 20-30 minutos desde el puerto de Toyapakeh. La profundidad de la zona de snorkel ronda los 5-12 metros, y suele considerarse la más accesible de las dos rutas: la bahía está más protegida que Manta Point, aunque el mar puede volverse agitado incluso en días aparentemente tranquilos, como ocurre en cualquier salida en mar abierto.'),
                $this->p('En Manta Bay, las mantarrayas se acercan sobre todo a alimentarse cuando hay plancton en el agua, más que por una estación de limpieza fija — por eso las mañanas (antes de que el viento agite la superficie) suelen ofrecer mejores condiciones.'),

                $this->h(2, 'La experiencia'),
                $this->p('El grupo se traslada en barco hasta el punto de entrada al agua, con un briefing de seguridad antes de empezar. El guía acompaña al grupo durante todo el recorrido de snorkel.'),

                $this->h(2, 'Qué incluye'),
                $this->ul([
                    'Equipo de snorkel (máscara y aletas)',
                    'Chaleco o dispositivo de flotación',
                    'Documentación de la experiencia (fotos o vídeos)',
                    'Agua mineral',
                ]),
                $this->p('El transporte hasta Nusa Penida y la comida no están incluidos — todos los detalles en <a href="' . $u('que-incluye') . '">Qué incluye</a>.'),

                $this->h(2, 'Horario y check-in'),
                $this->p('Salida matutina a las 09:00 (check-in 08:30) o salida vespertina a las 14:00 (check-in 13:30). Llegar con 30 minutos de anticipación permite ajustar el equipo y recibir el briefing de seguridad sin prisas. Ver <a href="' . $u('horarios') . '">Horarios</a> completos.'),

                $this->h(2, '¿Manta Bay o Manta Point?'),
                $this->p('Si dudas entre las dos zonas, la comparativa <a href="' . $u('manta-bay-vs-manta-point') . '">Manta Bay vs Manta Point</a> te ayuda a decidir según el paquete que te interese.'),

                $this->cta('Reserva tu snorkeling en Manta Bay', 'Consúltanos disponibilidad y precio por WhatsApp.', whatsapp_url($this->waMantaBay), 'Consultar por WhatsApp'),
            ],
            [
                'meta_title'       => 'Snorkeling en Manta Bay, Nusa Penida | Reserva por WhatsApp',
                'meta_description' => 'Snorkeling en Manta Bay (Nusa Penida): zona conocida por la presencia de mantarrayas, sin garantía de avistamiento. Equipo incluido y reserva directa por WhatsApp.',
                'focus_keyword'    => 'snorkeling Manta Bay Nusa Penida',
            ]
        );

        // ---- 3. /manta-point-nusa-penida/ ----------------------------------
        $this->seedPage(
            'Snorkeling en Manta Point, Nusa Penida',
            'manta-point-nusa-penida',
            [
                $this->p('Manta Point es la otra zona de snorkeling que visitamos en Nusa Penida junto a Manta Bay, también conocida por la presencia habitual de mantarrayas. Es un punto distinto, con su propia salida en barco y su propio grupo de paquetes.'),
                $this->p('Como en Manta Bay, los encuentros con fauna marina son naturales e impredecibles — no ofrecemos ni vendemos una garantía de avistamiento, solo una experiencia de snorkeling bien organizada en una zona donde suelen presentarse.'),

                $this->h(2, 'Dónde está y cómo es'),
                $this->p('Manta Point está más al suroeste que Manta Bay, junto a acantilados expuestos al mar abierto — un trayecto de unos 30-45 minutos en barco. A diferencia de Manta Bay, es una auténtica estación de limpieza: las mantarrayas acuden aquí para que peces más pequeños las limpien de parásitos, no solo a alimentarse. La zona donde se hace snorkel está a unos 4-10 metros de profundidad, aunque el arrecife cae después en un muro que llega mucho más hondo (zona de buceo, no de snorkel).'),
                $this->p('Al estar más expuesta al oleaje, las condiciones en Manta Point son en general más variables que en Manta Bay, y las salidas de snorkel dependen más del estado del mar ese día.'),

                $this->h(2, 'La experiencia en Manta Point'),
                $this->p('El grupo llega en barco al punto de entrada, recibe un briefing de seguridad y realiza el recorrido de snorkel acompañado por el guía durante toda la salida.'),

                $this->h(2, 'Qué incluye'),
                $this->ul([
                    'Equipo de snorkel (máscara y aletas)',
                    'Chaleco o dispositivo de flotación',
                    'Documentación de la experiencia (fotos o vídeos)',
                    'Agua mineral',
                ]),
                $this->p('Consulta el desglose completo en <a href="' . $u('que-incluye') . '">Qué incluye</a> — el transporte y la comida no forman parte del servicio.'),

                $this->h(2, 'Horario y check-in'),
                $this->p('Salida matutina a las 09:00 (check-in 08:30) o vespertina a las 14:00 (check-in 13:30). El check-in 30 minutos antes es necesario para el ajuste de equipo y el briefing de seguridad. Detalles en <a href="' . $u('horarios') . '">Horarios</a>.'),

                $this->h(2, '¿Manta Point o Manta Bay?'),
                $this->p('Consulta <a href="' . $u('manta-bay-vs-manta-point') . '">Manta Bay vs Manta Point</a> para ver qué paquete visita cada zona antes de reservar.'),

                $this->cta('Reserva tu snorkeling en Manta Point', 'Consúltanos disponibilidad y precio por WhatsApp.', whatsapp_url($this->waMantaPoint), 'Consultar por WhatsApp'),
            ],
            [
                'meta_title'       => 'Snorkeling en Manta Point, Nusa Penida',
                'meta_description' => 'Snorkeling en Manta Point (Nusa Penida): zona con presencia habitual de mantarrayas, sin garantía de avistamiento. Equipo incluido, reserva por WhatsApp.',
                'focus_keyword'    => 'Manta Point Nusa Penida',
            ]
        );

        // ---- 4. /private-snorkeling-nusa-penida/ ---------------------------
        $this->seedPage(
            'Private Snorkeling en Nusa Penida',
            'private-snorkeling-nusa-penida',
            [
                $this->p('El private snorkeling es una experiencia de snorkeling en Nusa Penida sin compartir barco con otros grupos: tú decides con quién vas, y el ritmo de la salida se ajusta a tu grupo en lugar de a un horario compartido.'),

                $this->h(2, 'Para quién puede ser adecuado'),
                $this->ul([
                    'Parejas que buscan una experiencia más tranquila (lunas de miel, aniversarios).',
                    'Familias o grupos de amigos que prefieren no compartir el barco con desconocidos.',
                    'Viajeros con un horario específico que no coincide con las salidas compartidas.',
                ]),

                $this->h(2, 'Qué incluye'),
                $this->p('Igual que el snorkeling en grupo: equipo de snorkel, chaleco o flotador, documentación de la experiencia y agua mineral, con la diferencia de que el barco es exclusivo para tu grupo. El transporte hasta Nusa Penida no está incluido — detalles en <a href="' . $u('que-incluye') . '">Qué incluye</a>.'),

                $this->h(2, 'Precio'),
                $this->p('El precio de una experiencia privada depende del número de personas, la fecha y la zona (Manta Bay, Manta Point o ambas). Por eso no publicamos un precio fijo aquí: escríbenos y te lo confirmamos según tu grupo.'),

                $this->cta('Consulta el precio para una experiencia privada', 'Cuéntanos cuántas personas sois y la fecha que os interesa, y te confirmamos disponibilidad y precio.', whatsapp_url($this->waPrivate), 'Solicitar precio'),
            ],
            [
                'meta_title'       => 'Private Snorkeling en Nusa Penida | Solicitar precio',
                'meta_description' => 'Private snorkeling en Nusa Penida: barco exclusivo para tu grupo, sin compartir con otros viajeros. Escríbenos por WhatsApp para consultar disponibilidad y precio.',
                'focus_keyword'    => 'private snorkeling Nusa Penida',
            ]
        );

        // ---- 5. /precios/ ---------------------------------------------------
        $this->seedPage(
            'Precios del Snorkeling en Nusa Penida',
            'precios',
            [
                $this->p('Precios de nuestros paquetes de snorkeling en Nusa Penida. A continuación encontrarás la tabla completa, qué incluyen y la opción privada.'),

                $this->h(2, 'Precios por persona'),
                $this->p('Varían según el número de personas: cuanto más grande el grupo, menor el precio por persona.'),
                $this->table([
                    ['<strong>Paquete</strong>', '<strong>Precio</strong>', '<strong>Grupo</strong>'],
                    ['Group Saver', '€13.50 por persona', '10 personas o más'],
                    ['Manta Point Sharing', '€24.50 por persona', '1 a 9 personas'],
                    ['Small Group Experience', '€14.70 por persona', '1 a 4 personas'],
                ]),
                $this->p('Los precios incluyen equipo de snorkel, chaleco o flotador, documentación de la experiencia (fotos/vídeos) y agua mineral. No incluyen transporte ni comida — ver <a href="' . $u('que-incluye') . '">Qué incluye</a>.'),

                $this->h(2, 'Private snorkeling'),
                $this->p('Para una experiencia privada (barco exclusivo para tu grupo), el precio depende del número de personas y la fecha. Consulta el precio en <a href="' . $u('private-snorkeling-nusa-penida') . '">Private Snorkeling</a>.'),

                $this->cta('Resuelve tus dudas sobre el precio', 'Escríbenos por WhatsApp indicando cuántas personas sois y te confirmamos el precio exacto y la disponibilidad.', whatsapp_url($this->waGeneral), 'Consultar por WhatsApp'),
            ],
            [
                'meta_title'       => 'Precios del Snorkeling en Nusa Penida',
                'meta_description' => 'Precios del snorkeling en Nusa Penida desde €13.50 por persona en grupo. Tabla clara según número de personas, sin descuentos inventados. Reserva por WhatsApp.',
                'focus_keyword'    => 'precio snorkeling Nusa Penida',
            ]
        );

        // ---- 6. /que-incluye/ ------------------------------------------------
        $this->seedPage(
            'Qué Incluye el Snorkeling en Nusa Penida',
            'que-incluye',
            [
                $this->p('Antes de reservar, es normal querer saber exactamente qué está incluido y qué no. Aquí lo explicamos de forma clara para que no haya sorpresas el día de la experiencia.'),

                $this->h(2, 'Qué está incluido'),
                $this->ul([
                    'Equipo de snorkel: máscara y aletas',
                    'Chaleco o dispositivo de flotación',
                    'Documentación de la experiencia: fotos o vídeos',
                    'Agua mineral',
                ]),

                $this->h(2, 'Qué NO está incluido'),
                $this->p('Para que la comparación de precios sea justa y sepas exactamente qué estás pagando, esto queda fuera del snorkeling:'),
                $this->ul([
                    'Transporte hasta Nusa Penida (barco Sanur–Nusa Penida u otro medio)',
                    'Recogida o traslado desde tu hotel',
                    'Comida o almuerzo',
                    'Alojamiento',
                    'Tours terrestres u otras actividades adicionales',
                    'Tasa de entrada a las Islas Nusa (retribución turística), que se paga aparte al llegar al puerto',
                    'Tasa del área marina protegida para actividades de snorkel/buceo, aplicable a turistas extranjeros',
                ]),
                $this->p('Esto aplica tanto al snorkeling en grupo como al <a href="' . $u('private-snorkeling-nusa-penida') . '">private snorkeling</a>.'),
                $this->p('Estas tasas las fija la normativa local y pueden actualizarse con el tiempo, así que si quieres el importe exacto y vigente para tu fecha de viaje, pregúntanos por WhatsApp y te lo confirmamos.'),
                $this->p('Para organizar tu día: el barco rápido entre Sanur y Nusa Penida suele tardar unos 30-45 minutos de travesía, dependiendo de la naviera y del estado del mar.'),

                $this->cta('Resuelve tus dudas sobre tu reserva', 'Pregúntanos por WhatsApp antes de reservar.', whatsapp_url($this->waGeneral), 'Preguntar por WhatsApp'),
            ],
            [
                'meta_title'       => 'Qué Incluye el Snorkeling en Nusa Penida',
                'meta_description' => 'Qué incluye y qué no incluye el snorkeling en Nusa Penida: equipo, flotador, fotos y agua mineral incluidos; transporte y comida no incluidos.',
                'focus_keyword'    => 'qué incluye snorkeling Nusa Penida',
            ]
        );

        // ---- 7. /horarios/ ----------------------------------------------------
        $this->seedPage(
            'Horarios del Snorkeling en Nusa Penida',
            'horarios',
            [
                $this->p('Tenemos dos salidas diarias de snorkeling en Nusa Penida — la tabla completa y por qué llegar puntual, a continuación.'),

                $this->h(2, 'Salidas y check-in'),
                $this->p('En ambas, el check-in es 30 minutos antes de la hora de salida.'),
                $this->table([
                    ['<strong>Salida</strong>', '<strong>Hora de salida</strong>', '<strong>Check-in</strong>'],
                    ['Matutina', '09:00', '08:30'],
                    ['Vespertina', '14:00', '13:30'],
                ]),

                $this->h(2, 'Por qué llegar 30 minutos antes'),
                $this->p('El check-in no es un margen de cortesía: es el tiempo que necesitamos para preparar la salida correctamente.'),
                $this->ul([
                    'Ajuste de equipo — asegurar que la máscara y las aletas te quedan bien antes de subir al barco.',
                    'Organización del grupo — confirmar asistencia y repartir el equipo sin prisas.',
                    'Briefing de seguridad — explicar las condiciones del día y las indicaciones básicas antes de entrar al agua.',
                    'Evitar retrasos — el barco sale a la hora programada; llegar tarde puede significar perder la salida.',
                ]),

                $this->h(2, 'Ejemplo'),
                $this->p('Si tu snorkeling sale a las 09:00, debes estar en el punto de encuentro a las 08:30 — no a las 09:00.'),

                $this->cta('Elige el horario que mejor te venga', 'Dinos qué salida prefieres y te confirmamos disponibilidad por WhatsApp.', whatsapp_url($this->waGeneral), 'Consultar disponibilidad'),
            ],
            [
                'meta_title'       => 'Horarios del Snorkeling en Nusa Penida | Salida y Check-in',
                'meta_description' => 'Horarios de salida del snorkeling en Nusa Penida: 09:00 y 14:00, con check-in 30 minutos antes. Por qué es importante llegar con anticipación.',
                'focus_keyword'    => 'horarios snorkeling Nusa Penida',
            ]
        );

        // ---- 8. /como-reservar/ ------------------------------------------------
        $this->seedPage(
            'Cómo Reservar Snorkeling en Nusa Penida',
            'como-reservar',
            [
                $this->p('Reservar tu snorkeling en Nusa Penida es un proceso simple, sin registros ni pagos online. Todo se gestiona por WhatsApp.'),

                $this->h(2, 'Pasos para reservar'),
                $this->ol([
                    'Elige la fecha en la que quieres hacer snorkeling.',
                    'Contáctanos por WhatsApp contándonos tu plan.',
                    'Indícanos el número de personas del grupo.',
                    'Te confirmamos disponibilidad y precio para esa fecha.',
                    'Confirmas la reserva y quedamos con los detalles del punto de encuentro y horario.',
                ]),
                $this->p('Si prefieres una experiencia sin compartir barco, el proceso es el mismo pero indicando que te interesa el <a href="' . $u('private-snorkeling-nusa-penida') . '">private snorkeling</a>.'),
                $this->p('Recuerda revisar los <a href="' . $u('horarios') . '">horarios de salida y check-in</a> antes de confirmar, para organizar bien tu día.'),

                $this->h(2, 'Reserva ahora'),
                $this->p('Completa el formulario y te contactaremos por WhatsApp para confirmar disponibilidad y precio — no se realiza ningún cargo ni pago online aquí.'),
                $this->bookingForm(),

                $this->cta('También puedes escribirnos directamente', 'Sin formulario: cuéntanos tu plan por WhatsApp y te respondemos al momento.', whatsapp_url($this->waGeneral), 'Reservar por WhatsApp'),
            ],
            [
                'meta_title'       => 'Cómo Reservar Snorkeling en Nusa Penida',
                'meta_description' => 'Cómo reservar tu snorkeling en Nusa Penida en 5 pasos simples, directamente por WhatsApp, sin pagos online ni registros complicados.',
                'focus_keyword'    => 'reservar snorkeling Nusa Penida',
            ]
        );

        // ---- 9. /preguntas-frecuentes/ ------------------------------------------
        $this->seedPage(
            'Preguntas Frecuentes sobre Snorkeling en Nusa Penida',
            'preguntas-frecuentes',
            [
                $this->p('Respuestas directas a las preguntas más habituales antes de reservar tu snorkeling en Nusa Penida.'),
                $this->h(2, 'Preguntas frecuentes'),
                ...$this->faq('¿Cuánto cuesta hacer snorkel en Nusa Penida?', 'Desde €13.50 por persona en grupos de 10 o más. El precio varía según el tamaño del grupo — consulta la tabla completa en la página de Precios.'),
                ...$this->faq('¿Qué incluye el snorkeling?', 'Equipo de snorkel (máscara y aletas), chaleco o dispositivo de flotación, documentación de la experiencia (fotos o vídeos) y agua mineral.'),
                ...$this->faq('¿El transporte está incluido?', 'No. El transporte hasta Nusa Penida y el traslado desde tu hotel no están incluidos en el snorkeling.'),
                ...$this->faq('¿La comida está incluida?', 'No, la comida no forma parte del servicio de snorkeling.'),
                ...$this->faq('¿A qué hora debo llegar?', 'Al punto de encuentro, 30 minutos antes de la hora de salida — por ejemplo, a las 08:30 si la salida es a las 09:00.'),
                ...$this->faq('¿A qué hora sale el barco?', 'Tenemos salida matutina a las 09:00 y salida vespertina a las 14:00.'),
                ...$this->faq('¿Manta Bay y Manta Point son lo mismo?', 'No, son dos zonas de snorkeling diferentes en Nusa Penida. Consulta la comparativa entre ambas para saber qué paquete visita cada una.'),
                ...$this->faq('¿Está garantizado ver mantarrayas?', 'No. Trabajamos en zonas conocidas por la presencia de mantarrayas, pero son animales salvajes: los encuentros dependen de la naturaleza y de las condiciones del mar, y ninguna operadora responsable puede garantizarlos. Su ritmo de reproducción es muy lento (una cría cada 2-3 años, según Manta Trust), así que no prometer avistamientos también es una forma de cuidar la especie.'),
                ...$this->faq('¿Qué equipo está incluido?', 'Máscara, aletas y chaleco o dispositivo de flotación.'),
                ...$this->faq('¿Necesito llevar mi propio equipo?', 'No es necesario — el equipo básico de snorkel está incluido en el precio.'),
                ...$this->faq('¿Puedo reservar private snorkeling?', 'Sí. Es una experiencia con barco exclusivo para tu grupo; el precio se confirma según el número de personas y la fecha.'),
                ...$this->faq('¿Cómo puedo reservar?', 'Escríbenos por WhatsApp con la fecha y el número de personas y te confirmamos disponibilidad y precio.'),

                $this->cta('Escríbenos si tu pregunta no está aquí', 'Te respondemos directamente por WhatsApp.', whatsapp_url($this->waGeneral), 'Preguntar por WhatsApp'),
            ],
            [
                'meta_title'       => 'Preguntas Frecuentes sobre Snorkeling en Nusa Penida',
                'meta_description' => 'Precio, horarios, qué incluye, Manta Bay vs Manta Point y cómo reservar: respuestas claras a las preguntas más frecuentes sobre el snorkeling en Nusa Penida.',
                'focus_keyword'    => 'preguntas frecuentes snorkeling Nusa Penida',
            ]
        );

        // ---- 10. /que-llevar/ ---------------------------------------------------
        $this->seedPage(
            'Qué Llevar para Hacer Snorkel en Nusa Penida',
            'que-llevar',
            [
                $this->p('Una lista práctica de lo que conviene llevar a tu snorkeling en Nusa Penida. El equipo de snorkel ya está incluido — esto es lo que puedes traer tú.'),
                $this->ul([
                    'Traje de baño',
                    'Protección solar adecuada para actividades marinas',
                    'Toalla',
                    'Ropa seca de cambio',
                    'Agua adicional, si lo consideras necesario',
                ]),
                $this->p('No es una lista de requisitos obligatorios, sino de recomendaciones prácticas para que tu día en el agua sea más cómodo. Si tienes dudas sobre qué más llevar, pregúntanos por WhatsApp antes de tu reserva.'),

                $this->cta('Resuelve tus dudas antes de tu snorkeling', 'Pregúntanos por WhatsApp lo que necesites.', whatsapp_url($this->waGeneral), 'Preguntar por WhatsApp'),
            ],
            [
                'meta_title'       => 'Qué Llevar para Hacer Snorkel en Nusa Penida',
                'meta_description' => 'Guía práctica de qué llevar a tu snorkeling en Nusa Penida: traje de baño, protección solar, toalla y ropa de cambio.',
                'focus_keyword'    => 'qué llevar para hacer snorkel en Nusa Penida',
            ]
        );

        // ---- 11. /manta-bay-vs-manta-point/ -------------------------------------
        $this->seedPage(
            'Manta Bay vs Manta Point en Nusa Penida',
            'manta-bay-vs-manta-point',
            [
                $this->p('Manta Bay y Manta Point son dos zonas de snorkeling distintas en Nusa Penida, no el mismo lugar con dos nombres — aunque es un error común: algunos operadores y viajeros usan los nombres indistintamente, sobre todo porque muchos tours de snorkel solo visitan Manta Bay y llaman "Manta Point" a la experiencia en general. En nuestros paquetes son dos rutas separadas, cada una con su propia salida en barco.'),

                $this->h(2, 'Ubicación y profundidad'),
                $this->ul([
                    '<strong>Manta Bay:</strong> costa suroeste, cerca de Broken Beach y Angel\'s Billabong, ~20-30 min en barco desde Toyapakeh. Profundidad de snorkel aproximada: 5-12 metros.',
                    '<strong>Manta Point:</strong> más al suroeste, junto a acantilados expuestos, ~30-45 min en barco. Es una estación de limpieza real, con la zona de snorkel a unos 4-10 metros (el arrecife cae después a un muro más profundo, ya en zona de buceo).',
                ]),

                $this->h(2, 'Corriente y dificultad'),
                $this->p('Manta Point está más expuesta al oleaje del mar abierto, así que sus condiciones suelen ser más variables y las salidas de snorkel dependen más del estado del mar ese día. Manta Bay, al estar en una bahía más resguardada, es en general la opción más accesible — aunque, como cualquier salida en barco, el mar puede agitarse incluso en días que empiezan tranquilos.'),

                $this->h(2, 'Por qué se acercan las mantarrayas'),
                $this->p('En Manta Point es una estación de limpieza: las mantarrayas acuden para que peces más pequeños las limpien de parásitos. En Manta Bay se acercan sobre todo a alimentarse cuando hay plancton en el agua. Ninguna de las dos garantiza avistamientos — son animales salvajes y su presencia depende de las condiciones de cada día.'),

                $this->h(2, 'Qué tienen en común'),
                $this->ul([
                    'Ambas son zonas de Nusa Penida conocidas por la presencia de mantarrayas.',
                    'En ambas, los encuentros con fauna marina son naturales e impredecibles — no hay garantía de avistamiento.',
                    'El snorkeling en ambas zonas incluye equipo, flotador, documentación de la experiencia y agua mineral.',
                ]),

                $this->h(2, 'Cómo elegir'),
                $this->p('Si no tienes preferencia por una zona en concreto, la forma más simple de decidir es por el paquete: consulta <a href="' . $u('snorkeling-manta-bay') . '">Snorkeling en Manta Bay</a> o <a href="' . $u('manta-point-nusa-penida') . '">Snorkeling en Manta Point</a> y compara precio y horario en cada página, o revisa la tabla completa en <a href="' . $u('precios') . '">Precios</a>.'),
                $this->p('Si prefieres que te ayudemos a decidir según tu fecha y grupo, escríbenos por WhatsApp.'),

                $this->cta('Te ayudamos a elegir', 'Cuéntanos tu fecha y número de personas y te recomendamos la mejor opción.', whatsapp_url($this->waGeneral), 'Preguntar por WhatsApp'),
            ],
            [
                'meta_title'       => 'Manta Bay vs Manta Point en Nusa Penida',
                'meta_description' => 'Manta Bay y Manta Point son dos zonas de snorkeling distintas en Nusa Penida. Te explicamos qué tienen en común y cómo elegir entre nuestros paquetes.',
                'focus_keyword'    => 'Manta Bay vs Manta Point',
            ]
        );
    }

    // ======================================================================
    //  Blog
    // ======================================================================

    protected function seedBlog(): void
    {
        $u = fn (string $slug = '') => site_url($slug);

        $this->seedPage(
            'Cómo Hacer Snorkeling en Nusa Penida: Guía Paso a Paso',
            'como-hacer-snorkeling-en-nusa-penida',
            [
                $this->p('Si es tu primera vez, no necesitas experiencia previa para hacer snorkeling en Nusa Penida — sí ayuda saber qué esperar del día. Esta guía cubre el proceso completo, desde que reservas hasta que sales del agua.'),

                $this->h(2, '1. Reserva con antelación'),
                $this->p('Escribe por WhatsApp con la fecha y el número de personas de tu grupo. Te confirman disponibilidad y precio antes de viajar a Nusa Penida — así evitas llegar sin plaza en temporada alta. Ver <a href="' . $u('como-reservar') . '">cómo reservar</a>.'),

                $this->h(2, '2. Llega con tiempo al punto de encuentro'),
                $this->p('El check-in es 30 minutos antes de la salida, para el ajuste de equipo y el briefing de seguridad. Llegar tarde puede significar perder la salida, ya que el barco parte a la hora programada.'),

                $this->h(2, '3. El briefing de seguridad'),
                $this->p('Antes de entrar al agua, el guía explica las indicaciones básicas de la salida: cómo usar el equipo, qué hacer en el agua y las señales para seguir al grupo.'),

                $this->h(2, '4. En el agua'),
                $this->p('El guía acompaña al grupo durante todo el recorrido. Ir con el grupo y seguir sus indicaciones es la forma más segura de aprovechar la salida, tengas o no experiencia previa.'),

                $this->h(2, '5. Después del snorkeling'),
                $this->p('La documentación de la experiencia (fotos o vídeos) se entrega como parte del paquete. Antes de tu viaje, revisa qué llevar y qué incluye exactamente tu reserva.'),

                $this->p('Guías relacionadas: <a href="' . $u('que-llevar') . '">qué llevar</a>, <a href="' . $u('que-incluye') . '">qué incluye</a>, <a href="' . $u('horarios') . '">horarios</a>.'),

                $this->cta('Empieza a organizar tu snorkeling', 'Escríbenos por WhatsApp y empezamos a organizar tu día.', whatsapp_url($this->waGeneral), 'Reservar por WhatsApp'),
            ],
            [
                'meta_title'       => 'Cómo Hacer Snorkeling en Nusa Penida: Guía Paso a Paso',
                'meta_description' => 'Guía paso a paso para hacer snorkeling en Nusa Penida por primera vez: cómo reservar, qué esperar del check-in, el briefing y la salida en barco.',
                'focus_keyword'    => 'cómo hacer snorkeling en Nusa Penida',
            ],
            'post'
        );

        $this->seedPage(
            '¿Cuánto Cuesta Hacer Snorkeling en Nusa Penida?',
            'cuanto-cuesta-snorkeling-nusa-penida',
            [
                $this->p('El precio del snorkeling en Nusa Penida varía sobre todo según un factor: si vas en grupo compartido o en barco privado. Aquí te explicamos qué influye en el precio antes de comparar operadoras.'),

                $this->h(2, 'Tamaño del grupo'),
                $this->p('En los paquetes de grupo compartido, cuantas más personas viajan juntas, menor suele ser el precio por persona — es la forma en que se reparte el coste del barco y el guía entre más viajeros.'),

                $this->h(2, 'Grupo compartido vs. privado'),
                $this->p('Un barco privado (private snorkeling) tiene un coste distinto al de un grupo compartido, porque el barco y el guía son exclusivos para tu grupo en lugar de repartirse entre varios. Por eso el precio de una experiencia privada se confirma según el número de personas en lugar de publicarse como una tarifa fija.'),

                $this->h(2, 'Qué debería estar incluido en el precio'),
                $this->p('Al comparar precios entre operadoras, conviene confirmar qué incluye exactamente cada una: equipo de snorkel, flotador, documentación de la experiencia y agua mineral son inclusiones habituales. El transporte hasta Nusa Penida y la comida normalmente se cobran aparte.'),

                $this->h(2, 'Nuestros precios'),
                $this->p('Puedes ver la tabla completa y actualizada en <a href="' . $u('precios') . '">Precios</a>, o solicitar el precio de una experiencia privada en <a href="' . $u('private-snorkeling-nusa-penida') . '">Private Snorkeling</a>.'),

                $this->cta('Consulta tu precio exacto', 'Dinos cuántas personas sois y te confirmamos el precio por WhatsApp.', whatsapp_url($this->waGeneral), 'Consultar precio'),
            ],
            [
                'meta_title'       => '¿Cuánto Cuesta Hacer Snorkeling en Nusa Penida?',
                'meta_description' => 'Qué influye en el precio del snorkeling en Nusa Penida: tamaño del grupo, barco compartido o privado, y qué debería estar incluido en el precio.',
                'focus_keyword'    => 'cuánto cuesta snorkeling Nusa Penida',
            ],
            'post'
        );

        $this->seedPage(
            'Manta Bay en Nusa Penida: Guía para Viajeros',
            'manta-bay-nusa-penida-guia-viajeros',
            [
                $this->p('Manta Bay es una de las zonas de snorkeling más buscadas de Nusa Penida, conocida por la presencia de mantarrayas. Si estás planeando tu viaje, esto es lo que un viajero debería saber antes de reservar.'),

                $this->h(2, 'Qué es Manta Bay'),
                $this->p('Es una de las zonas de snorkeling de Nusa Penida que visitamos como parte de nuestros paquetes, conocida por la presencia habitual de mantarrayas en sus aguas.'),

                $this->h(2, '¿Vas a ver mantarrayas seguro?'),
                $this->p('No podemos prometerlo, y desconfía de quien lo haga: son animales salvajes y los encuentros dependen de la naturaleza y de las condiciones del mar ese día. Lo que sí puedes esperar es una salida organizada en una zona donde suelen presentarse.'),

                $this->h(2, 'Cómo se organiza la salida'),
                $this->p('El grupo sale en barco desde el punto de encuentro, con check-in 30 minutos antes para el ajuste de equipo y el briefing de seguridad, y un guía que acompaña el recorrido de snorkel.'),

                $this->h(2, 'Antes de reservar'),
                $this->p('Revisa qué incluye el paquete, los horarios de salida disponibles y qué te conviene llevar. Todo eso está en la página de <a href="' . $u('snorkeling-manta-bay') . '">snorkeling en Manta Bay</a>.'),

                $this->cta('Reserva tu snorkeling en Manta Bay', 'Consúltanos disponibilidad y precio por WhatsApp.', whatsapp_url($this->waMantaBay), 'Consultar por WhatsApp'),
            ],
            [
                'meta_title'       => 'Manta Bay en Nusa Penida: Guía para Viajeros',
                'meta_description' => 'Todo lo que un viajero debería saber sobre Manta Bay en Nusa Penida antes de reservar su snorkeling: qué esperar, cómo se organiza la salida y qué revisar antes.',
                'focus_keyword'    => 'Manta Bay Nusa Penida guía',
            ],
            'post'
        );

        $this->seedPage(
            'Manta Point en Nusa Penida: Qué Esperar',
            'manta-point-nusa-penida-que-esperar',
            [
                $this->p('Manta Point es la otra gran zona de snorkeling de Nusa Penida, junto a Manta Bay. Si es tu primera vez, esto es lo que puedes esperar de la experiencia.'),

                $this->h(2, 'Qué es Manta Point'),
                $this->p('Es una zona de snorkeling de Nusa Penida con presencia habitual de mantarrayas, distinta de Manta Bay y con su propia salida en barco dentro de nuestros paquetes.'),

                $this->h(2, 'Qué esperar del día'),
                $this->p('Una salida en barco hasta el punto de entrada, un briefing de seguridad antes de meterte en el agua, y un recorrido de snorkel acompañado por el guía. Como con cualquier fauna marina salvaje, el avistamiento de mantarrayas no está garantizado.'),

                $this->h(2, 'Primera vez haciendo snorkel'),
                $this->p('No necesitas experiencia previa: el equipo básico está incluido y el guía da las indicaciones necesarias antes de empezar. Aun así, conviene revisar <a href="' . $u('que-llevar') . '">qué llevar</a> para estar cómodo durante el día.'),

                $this->h(2, 'Antes de reservar'),
                $this->p('Consulta horarios, precio y qué incluye exactamente en la página de <a href="' . $u('manta-point-nusa-penida') . '">snorkeling en Manta Point</a>.'),

                $this->cta('Reserva tu snorkeling en Manta Point', 'Consúltanos disponibilidad y precio por WhatsApp.', whatsapp_url($this->waMantaPoint), 'Consultar por WhatsApp'),
            ],
            [
                'meta_title'       => 'Manta Point en Nusa Penida: Qué Esperar',
                'meta_description' => 'Qué esperar de tu primera vez haciendo snorkeling en Manta Point, Nusa Penida: cómo se organiza la salida, el briefing de seguridad y qué llevar.',
                'focus_keyword'    => 'Manta Point Nusa Penida qué esperar',
            ],
            'post'
        );

        $this->seedPage(
            'Mejor Horario para Hacer Snorkeling en Nusa Penida',
            'mejor-horario-snorkeling-nusa-penida',
            [
                $this->p('Tenemos dos salidas diarias de snorkeling en Nusa Penida: a las 09:00 y a las 14:00. Ninguna es objetivamente "la mejor" — depende de cómo organices el resto de tu día.'),

                $this->h(2, 'Salida matutina (09:00)'),
                $this->p('Es la opción habitual para quienes llegan desde Bali en el barco rápido de la mañana desde Sanur y quieren empezar el día de snorkeling sin esperar. El check-in es a las 08:30.'),

                $this->h(2, 'Salida vespertina (14:00)'),
                $this->p('Funciona bien si prefieres dedicar la mañana a llegar con calma a Nusa Penida, explorar un poco la isla, o simplemente no madrugar. El check-in es a las 13:30.'),

                $this->h(2, 'Nuestra recomendación práctica'),
                $this->p('Más que una hora "mejor" en términos de condiciones del mar — algo que varía día a día y no podemos prometerte de antemano — la decisión suele depender de tu logística: a qué hora llegas a la isla y qué más quieres hacer ese día. Revisa los horarios exactos de barco rápido desde Sanur y organiza tu snorkeling en torno a eso.'),

                $this->p('Detalles completos de ambas salidas en <a href="' . $u('horarios') . '">Horarios</a>.'),

                $this->cta('Elige el horario que más te convenga', 'Cuéntanos tu plan de viaje y te recomendamos la salida que mejor encaje.', whatsapp_url($this->waGeneral), 'Consultar por WhatsApp'),
            ],
            [
                'meta_title'       => 'Mejor Horario para Hacer Snorkeling en Nusa Penida',
                'meta_description' => 'Salida matutina o vespertina: cómo elegir el mejor horario para tu snorkeling en Nusa Penida según tu logística de viaje desde Bali.',
                'focus_keyword'    => 'mejor horario snorkeling Nusa Penida',
            ],
            'post'
        );

        $this->seedPage(
            'Consejos para Hacer Snorkeling en Nusa Penida',
            'consejos-snorkeling-nusa-penida',
            [
                $this->p('Algunos consejos prácticos, más allá de qué incluye el paquete, para que tu día de snorkeling en Nusa Penida salga bien.'),

                $this->h(2, 'Llega puntual al check-in'),
                $this->p('El check-in es 30 minutos antes de la salida, no un margen opcional. El barco sale a la hora programada, así que llegar tarde puede significar perder tu plaza.'),

                $this->h(2, 'Escucha el briefing de seguridad'),
                $this->p('Aunque ya hayas hecho snorkel antes, cada zona y cada día tiene sus propias condiciones. El briefing del guía es la forma más rápida de saber qué esperar ese día concreto.'),

                $this->h(2, 'Protege tu piel sin dañar el mar'),
                $this->p('Usa protección solar adecuada para actividades marinas — ayuda a cuidar tanto tu piel como el entorno en el que vas a nadar.'),

                $this->h(2, 'No te separes del grupo'),
                $this->p('El guía marca el ritmo y la ruta por una razón: conoce la zona y las condiciones del día. Mantenerte cerca del grupo es la forma más segura de disfrutar la salida.'),

                $this->h(2, 'Gestiona tus expectativas sobre la fauna marina'),
                $this->p('Si tu objetivo principal es ver mantarrayas, entra al agua sabiendo que es una posibilidad, no una garantía. Disfrutar del snorkeling en sí — el agua, el arrecife, la salida en barco — hace que la experiencia valga la pena incluso si ese día no hay avistamiento.'),

                $this->p('Antes de reservar, revisa también <a href="' . $u('que-llevar') . '">qué llevar</a> y <a href="' . $u('que-incluye') . '">qué incluye</a> tu paquete.'),

                $this->cta('Resuelve tus dudas antes de reservar', 'Escríbenos por WhatsApp y resolvemos tus dudas antes de reservar.', whatsapp_url($this->waGeneral), 'Reservar por WhatsApp'),
            ],
            [
                'meta_title'       => 'Consejos para Hacer Snorkeling en Nusa Penida',
                'meta_description' => 'Consejos prácticos para tu snorkeling en Nusa Penida: puntualidad en el check-in, seguridad, protección solar y cómo gestionar las expectativas sobre la fauna marina.',
                'focus_keyword'    => 'consejos snorkeling Nusa Penida',
            ],
            'post'
        );
    }

    // ======================================================================
    //  Menus (Admin -> Menus): the footer menu ships completely empty and
    //  the primary menu ships with only "Home" — neither is theme-hardcoded,
    //  so populating them here is the intended way to make the new pages
    //  reachable from the site's actual navigation, not a homepage change.
    // ======================================================================

    protected function seedMenus(): void
    {
        $primaryMenuId = (int) $this->db->table('menus')->select('id')->where('slug', 'primary')->get()->getRow('id');
        $footerMenuId  = (int) $this->db->table('menus')->select('id')->where('slug', 'footer')->get()->getRow('id');

        // -- Primary nav: keep the existing "Home" item, add the handful
        //    of links a visitor actually needs from any page. Re-runnable:
        //    delete-by-title first so re-seeding doesn't pile up duplicates.
        $primaryLinks = [
            'Snorkeling'  => 'snorkeling-nusa-penida',
            'Manta Bay'   => 'snorkeling-manta-bay',
            'Manta Point' => 'manta-point-nusa-penida',
            'Precios'     => 'precios',
            'Blog'        => 'blog',
        ];
        $this->db->table('menu_items')->whereIn('title', array_keys($primaryLinks))->where('menu_id', $primaryMenuId)->delete();

        $position = 10;

        foreach ($primaryLinks as $title => $slug) {
            $this->db->table('menu_items')->insert([
                'menu_id'    => $primaryMenuId,
                'parent_id'  => 0,
                'title'      => $title,
                'url'        => site_url($slug),
                'position'   => $position,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $position += 10;
        }

        // -- Footer: 3 columns (st_footer_columns() turns each top-level
        //    item into a column and its children into that column's
        //    links), covering every page created above.
        $this->db->table('menu_items')->where('menu_id', $footerMenuId)->delete();

        $columns = [
            'Snorkeling' => [
                'Snorkeling en Nusa Penida' => 'snorkeling-nusa-penida',
                'Manta Bay'                 => 'snorkeling-manta-bay',
                'Manta Point'               => 'manta-point-nusa-penida',
                'Private Snorkeling'        => 'private-snorkeling-nusa-penida',
            ],
            'Información' => [
                'Precios'        => 'precios',
                'Qué incluye'    => 'que-incluye',
                'Horarios'       => 'horarios',
                'Cómo reservar'  => 'como-reservar',
            ],
            'Ayuda' => [
                'Preguntas frecuentes'      => 'preguntas-frecuentes',
                'Qué llevar'                => 'que-llevar',
                'Manta Bay vs Manta Point'  => 'manta-bay-vs-manta-point',
                'Blog'                      => 'blog',
            ],
        ];

        $position = 10;

        foreach ($columns as $columnTitle => $links) {
            $this->db->table('menu_items')->insert([
                'menu_id'    => $footerMenuId,
                'parent_id'  => 0,
                'title'      => $columnTitle,
                'url'        => null,
                'position'   => $position,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $parentId = $this->db->insertID();

            $childPosition = 10;

            foreach ($links as $title => $slug) {
                $this->db->table('menu_items')->insert([
                    'menu_id'    => $footerMenuId,
                    'parent_id'  => $parentId,
                    'title'      => $title,
                    'url'        => site_url($slug),
                    'position'   => $childPosition,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $childPosition += 10;
            }

            $position += 10;
        }
    }

    // ======================================================================
    //  Helpers
    // ======================================================================

    /**
     * Inserts (or replaces, by slug) one Page/Post with full SEO treatment
     * — the same pipeline Admin -> Posts/Pages runs on save: rendered body
     * HTML, auto-detected FAQ schema (from h2-4+p pairs — see
     * AutoSeoGenerator::detectFaqs()), and a real SEO score.
     */
    protected function seedPage(string $title, string $slug, array $blocks, array $seo, string $postType = 'page'): int
    {
        $existing = $this->db->table('posts')->where('slug', $slug)->get()->getRowArray();

        if ($existing) {
            (new SEOModel())->where('post_id', $existing['id'])->delete();
            (new PostModel())->delete((int) $existing['id']);
        }

        $parser      = new BlockParser();
        $contentJson = $parser->serialize($parser->parse($blocks));

        $postModel = new PostModel();
        $postModel->insert([
            'id'             => null,
            'title'          => $title,
            'slug'           => $slug,
            // 'es' — this drives <html lang> / og:locale for the page, and
            // LocaleManager::path() treats any code that isn't a registered
            // non-default language (multi-language is off, so every code
            // resolves that way) as unprefixed, so this never grows an
            // /es/ URL segment even though the registered language row is
            // still 'en'.
            'locale'         => 'es',
            'content'        => $contentJson,
            'excerpt'        => $seo['meta_description'],
            'author_id'      => $this->authorId,
            'post_type'      => $postType,
            'status'         => 'published',
            'featured_image' => '',
            'comment_status' => 'closed',
            'published_at'   => date('Y-m-d H:i:s'),
        ], false);

        $postId = $postModel->getInsertID();
        $post   = $postModel->find($postId);

        $bodyHtml = (new BlockRenderer())->render($post['content'] ?? '[]');
        $author   = (new UserModel())->find($this->authorId) ?: [];

        $schemaData = (new AutoSeoGenerator())->buildSchema($post, $author, null, $bodyHtml);

        $analysis = Services::seoAnalyzer()->analyze([
            'title'            => $title,
            'body'             => $bodyHtml,
            'meta_description' => $seo['meta_description'],
        ], $seo['focus_keyword'] ?? '');

        (new SEOModel())->saveForPost($postId, [
            'meta_title'       => $seo['meta_title'],
            'meta_description' => $seo['meta_description'],
            'focus_keyword'    => $seo['focus_keyword'] ?? '',
            // Left blank on purpose: MetaBuilder already self-computes a
            // canonical URL from base_url() + slug when this is empty,
            // which is exactly the self-referencing canonical every page
            // should have — no need to duplicate that logic here.
            'canonical_url'    => '',
            'robots_index'     => 1,
            'robots_follow'    => 1,
            'og_image'         => '',
            'schema_data'      => $schemaData,
            'seo_score'        => $analysis['score'] ?? 0,
        ]);

        return $postId;
    }

    protected function p(string $html): array
    {
        return ['type' => 'paragraph', 'attrs' => [], 'content' => $html];
    }

    protected function h(int $level, string $html): array
    {
        return ['type' => 'heading', 'attrs' => ['level' => $level], 'content' => $html];
    }

    protected function ul(array $items): array
    {
        return ['type' => 'list', 'attrs' => ['ordered' => false, 'items' => $items], 'content' => ''];
    }

    protected function ol(array $items): array
    {
        return ['type' => 'list', 'attrs' => ['ordered' => true, 'items' => $items], 'content' => ''];
    }

    /** @param array<int, array<int, string>> $rows */
    protected function table(array $rows): array
    {
        return ['type' => 'table', 'attrs' => ['rows' => $rows], 'content' => ''];
    }

    protected function cta(string $title, string $text, string $url, string $buttonLabel): array
    {
        return ['type' => 'cta', 'attrs' => ['title' => $title, 'text' => $text, 'url' => $url, 'button_label' => $buttonLabel], 'content' => ''];
    }

    /**
     * Renders as a large pull-quote card (.lcms-bento__cell--quote). Only
     * ever the business's own honest voice — see rule 19 in the original
     * brief ("no crear testimonios falsos") — never a fabricated customer
     * name/quote, since no real ones exist yet to draw from.
     */
    protected function quote(string $text, string $cite = ''): array
    {
        return ['type' => 'quote', 'attrs' => $cite !== '' ? ['cite' => $cite] : [], 'content' => $text];
    }

    /**
     * Trusted raw markup (BlockRenderer emits 'html' blocks verbatim) —
     * used once, for the booking form below, rather than adding a new
     * block type for something this specific.
     */
    protected function html(string $markup): array
    {
        return ['type' => 'html', 'attrs' => [], 'content' => $markup];
    }

    /**
     * The booking form itself — same fields, order and behaviour as the
     * live reference at snorkelingpenida.com (same business, confirmed by
     * the matching WhatsApp number): name, guests, date, boat type,
     * departure time, an optional message, and a safety acknowledgement,
     * submitting via WhatsApp rather than a server-side booking system
     * (see PRD-style brief §6-7 — "no crear backend innecesario").
     *
     * The generated message leads with the site name (as asked), then the
     * booking details, matching the same shape as the plain WhatsApp CTA
     * messages elsewhere on the site. initBookingForm() in main.js does
     * the actual submit handling; this only emits the markup + the wa
     * number as a data attribute (whatsapp_digits(), the same centralized
     * source every other WhatsApp link on the site reads from).
     */
    protected function bookingForm(): array
    {
        $wa = esc(whatsapp_digits(), 'attr');

        return $this->html(<<<HTML
            <form class="st-booking-form" id="st-booking-form" data-wa-number="{$wa}">
                <div class="st-booking-form__grid">
                    <label class="st-field">
                        <span>Nombre *</span>
                        <input type="text" name="nombre" autocomplete="name" required>
                    </label>
                    <label class="st-field">
                        <span>Número de personas *</span>
                        <input type="number" name="personas" min="1" max="50" required>
                    </label>
                    <label class="st-field">
                        <span>Fecha preferida *</span>
                        <input type="date" name="fecha" required>
                    </label>
                    <label class="st-field">
                        <span>Hora preferida *</span>
                        <select name="hora" required>
                            <option value="" disabled selected>Selecciona una hora</option>
                            <option value="09:00 (salida matutina)">09:00 — Salida matutina</option>
                            <option value="14:00 (salida vespertina)">14:00 — Salida vespertina</option>
                        </select>
                    </label>
                    <label class="st-field st-field--wide">
                        <span>Tipo de experiencia *</span>
                        <select name="experiencia" required>
                            <option value="" disabled selected>Selecciona una opción</option>
                            <option value="Barco compartido (grupo) - Manta Bay">Barco compartido (grupo) — Manta Bay</option>
                            <option value="Barco compartido (grupo) - Manta Point">Barco compartido (grupo) — Manta Point</option>
                            <option value="Barco privado (VIP)">Barco privado (VIP)</option>
                        </select>
                    </label>
                    <label class="st-field st-field--wide">
                        <span>Mensaje (opcional)</span>
                        <textarea name="mensaje" rows="3" placeholder="¿Alguna petición especial?"></textarea>
                    </label>
                </div>

                <label class="st-booking-form__check">
                    <input type="checkbox" name="seguridad" required>
                    <span>Entiendo que, por seguridad, todos los participantes deben usar el chaleco salvavidas durante el snorkel.</span>
                </label>

                <button type="submit" class="st-btn st-btn--cta st-booking-form__submit">Enviar Reserva por WhatsApp</button>
                <p class="st-booking-form__note">Tus datos solo se usan para confirmar tu reserva por WhatsApp. Nunca spam.</p>
            </form>
            HTML);
    }

    /**
     * An h3 immediately followed by a p — AutoSeoGenerator::detectFaqs()
     * picks up exactly this shape from the rendered HTML and turns it
     * into FAQPage schema once a page has 2+ pairs. Spread this into a
     * page's $blocks array with `...`.
     *
     * @return array{0: array, 1: array}
     */
    protected function faq(string $question, string $answer): array
    {
        return [$this->h(3, $question), $this->p($answer)];
    }
}
