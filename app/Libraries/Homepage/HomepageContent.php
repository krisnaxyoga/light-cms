<?php

namespace App\Libraries\Homepage;

use App\Models\SettingModel;
use Config\Services;

/**
 * Admin-editable copy, images and section toggles for the front page,
 * the blog listing header and the single-post CTA box.
 *
 * The whole document is stored as one JSON blob in the `settings` table
 * (key `homepage_content`, not autoloaded — it is only read on a cache
 * miss of the front page). Anything the admin has not saved yet falls
 * back to defaults(), so a fresh install renders a complete page and
 * a later schema addition never breaks an older saved document.
 *
 * schema() drives both the admin form normalisation (fromPost()) and
 * the merge rules: scalar keys and nested objects merge key-by-key,
 * while lists ("repeaters") are replaced wholesale by whatever the
 * admin saved — otherwise a deleted testimonial would resurrect from
 * the defaults.
 */
class HomepageContent
{
    public const SETTING_KEY = 'homepage_content';

    /** @var array<string, mixed>|null per-request memo */
    protected static ?array $loaded = null;

    /**
     * Field types per section. `list` entries carry the item schema.
     */
    public static function schema(): array
    {
        return [
            'seo' => [
                'meta_title'       => 'text',
                'meta_description' => 'text',
                'canonical_url'    => 'text',
                'og_image'         => 'text',
                'html_lang'        => 'text',
            ],
            'hero' => [
                'enabled'    => 'bool',
                'title'      => 'text',
                'accent'     => 'text',
                'tagline'    => 'text',
                'image'      => 'text',
                'image_alt'  => 'text',
                // Numbered "01 / 02 / 03…" cards shown over the photo,
                // top to bottom, in the order saved here.
                'highlights' => ['list' => ['title' => 'text', 'text' => 'text', 'link_label' => 'text', 'link_url' => 'text']],
            ],
            'why' => [
                'enabled'    => 'bool',
                'eyebrow'    => 'text',
                'heading'    => 'text',
                'subheading' => 'text',
                'items'      => ['list' => ['icon' => 'text', 'title' => 'text', 'text' => 'text']],
            ],
            'pricing' => [
                'enabled'    => 'bool',
                'heading'    => 'text',
                'subheading' => 'text',
                'packages'   => ['list' => [
                    'name'         => 'text',
                    'ribbon'       => 'text',
                    'ribbon_style' => 'text',
                    'featured'     => 'bool',
                    'icon'         => 'text',
                    'audience'     => 'text',
                    'price_old'    => 'text',
                    'price_now'    => 'text',
                    'unit'         => 'text',
                    'features'     => 'lines',
                    'rating'       => 'text',
                    'cta_label'    => 'text',
                    'cta_url'      => 'text',
                ]],
            ],
            'compare' => [
                'enabled'     => 'bool',
                'heading'     => 'text',
                'col_feature' => 'text',
                'col_a'       => 'text',
                'col_b'       => 'text',
                'rows'        => ['list' => ['feature' => 'text', 'a' => 'text', 'b' => 'text']],
                'note'        => 'text',
            ],
            'schedule' => [
                'enabled'      => 'bool',
                'heading'      => 'text',
                'subheading'   => 'text',
                'sessions'     => ['list' => ['icon' => 'text', 'title' => 'text', 'checkin' => 'text', 'best_for' => 'text', 'recommended' => 'text']],
                'notice_title' => 'text',
                'notice_items' => 'lines',
            ],
            'steps' => [
                'enabled' => 'bool',
                'heading' => 'text',
                'items'   => ['list' => ['icon' => 'text', 'title' => 'text', 'text' => 'text']],
            ],
            'checklist' => [
                'enabled'        => 'bool',
                'heading'        => 'text',
                'subheading'     => 'text',
                'included_title' => 'text',
                'included'       => 'lines',
                'excluded_title' => 'text',
                'excluded'       => 'lines',
            ],
            'packing' => [
                'enabled'    => 'bool',
                'heading'    => 'text',
                'subheading' => 'text',
                'items'      => ['list' => ['icon' => 'text', 'label' => 'text']],
                'tip'        => 'text',
            ],
            'testimonials' => [
                'enabled'    => 'bool',
                'heading'    => 'text',
                'subheading' => 'text',
                'items'      => ['list' => ['quote' => 'text', 'author' => 'text', 'location' => 'text', 'rating' => 'text']],
            ],
            'faq' => [
                'enabled'    => 'bool',
                'heading'    => 'text',
                'subheading' => 'text',
                'items'      => ['list' => ['question' => 'text', 'answer' => 'text']],
            ],
            'final_cta' => [
                'enabled'             => 'bool',
                'heading'             => 'text',
                'subheading'          => 'text',
                'urgency'             => 'text',
                'cta_primary_label'   => 'text',
                'cta_primary_url'     => 'text',
                'cta_secondary_label' => 'text',
                'cta_secondary_url'   => 'text',
                'whatsapp_label'      => 'text',
                'whatsapp_url'        => 'text',
                'hours'               => 'text',
            ],
            'blog' => [
                'heading'          => 'text',
                'intro'            => 'text',
                'meta_title'       => 'text',
                'meta_description' => 'text',
            ],
            'single_cta' => [
                'enabled'   => 'bool',
                'heading'   => 'text',
                'text'      => 'text',
                'cta_label' => 'text',
                'cta_url'   => 'text',
            ],
        ];
    }

    public static function defaults(): array
    {
        // Single source of truth: Admin -> Settings -> Contact
        // (app/Helpers/whatsapp_helper.php). Was a literal string here
        // before, duplicated separately in theme.json.
        $wa = whatsapp_url();

        return [
            'seo' => [
                'meta_title'       => 'Tour de Snorkel en Nusa Penida | Mantarrayas desde €13.50',
                'meta_description' => 'Snorkel en Nusa Penida con operadores locales: Manta Bay, Crystal Bay y Gamat Bay. Grupos reducidos, guías Rescue Diver, fotos GoPro incluidas y reserva flexible sin pago anticipado.',
                'canonical_url'    => '',
                'og_image'         => '',
                'html_lang'        => 'es',
            ],
            'hero' => [
                'enabled'   => true,
                'title'     => 'Snorkel en Nusa Penida',
                'accent'    => 'Nusa Penida',
                'tagline'   => 'Nada junto a mantarrayas en Manta Bay, Crystal Bay y Gamat Bay — guías locales certificados, fotos GoPro incluidas, desde €13.50.',
                // Ships pointing at the Super Travel theme's own sample
                // photo (hardcoded to that theme's folder, not theme_url(),
                // so the default stays correct even if another theme is
                // ever made active) — Admin -> Homepage can swap it for
                // any Media Library image at any time.
                'image'     => base_url('themes/super-travel/assets/DJI_20260422100314_0002_D.JPG'),
                'image_alt' => 'Snorkel en Nusa Penida con mantarrayas y aguas cristalinas',
                'highlights' => [
                    ['title' => 'Nullam auctor', 'text' => 'Integer nunc quam nec felis dictum accumsan.', 'link_label' => 'Ver más', 'link_url' => '#'],
                    ['title' => 'Integer viverra', 'text' => 'Nisi sapien rhoncus tortor, sit amet lobortis diam.', 'link_label' => 'Ver más', 'link_url' => '#'],
                    ['title' => 'Vivamus varius', 'text' => 'Nullam eget aliquam tempus, purus ligula lobortis.', 'link_label' => 'Ver más', 'link_url' => '#'],
                ],
            ],
            'why' => [
                'enabled'    => true,
                'eyebrow'    => 'Por qué elegirnos',
                'heading'    => 'Más Que un Tour, Una Inmersión en el Paraíso',
                'subheading' => 'Descubre por qué miles de viajeros nos eligen para explorar las aguas de Nusa Penida',
                'items'      => [
                    ['icon' => 'award', 'title' => 'Experiencia Comprobada', 'text' => 'Nuestros guías han liderado cientos de tours en Nusa Penida. Conocen los patrones de las mareas, el comportamiento de las mantarrayas y los secretos para maximizar tus avistamientos.'],
                    ['icon' => 'ship', 'title' => 'Flota Moderna y Segura', 'text' => 'Nuestras lanchas rápidas están equipadas con todo lo necesario: chalecos salvavidas, botiquines completos y comunicación constante con tierra para monitorear el clima.'],
                    ['icon' => 'fish', 'title' => 'Puntos Estratégicos', 'text' => 'No te llevamos a cualquier lugar. Elegimos cada punto según la hora del día, las mareas y la temporada para asegurar que veas la mejor vida marina posible.'],
                    ['icon' => 'camera', 'title' => 'Recuerdos Para Toda la Vida', 'text' => 'Nuestro equipo captura tus mejores momentos bajo el agua con cámaras GoPro de última generación. Te enviamos todas las fotos sin costo adicional.'],
                    ['icon' => 'cash', 'title' => 'Transparencia Total', 'text' => 'El precio que ves es el precio que pagas. Sin sorpresas, sin cargos ocultos. Todo está claramente detallado antes de reservar.'],
                ],
            ],
            'pricing' => [
                'enabled'    => true,
                'heading'    => 'Elige la Experiencia Perfecta para Ti',
                'subheading' => 'Desde aventuras en grupo hasta escapadas privadas, tenemos el paquete ideal para tu estilo de viaje',
                'packages'   => [
                    [
                        'name' => 'Group Saver', 'ribbon' => 'La Opción Más Económica', 'ribbon_style' => 'accent', 'featured' => true,
                        'icon' => '',
                        'audience'  => 'Mochileros, grupos de amigos, familias numerosas que buscan la mejor relación calidad-precio.',
                        'price_old' => '€24.50', 'price_now' => '€13.50', 'unit' => 'por persona · grupos de 10 o más',
                        'features'  => [
                            'Recorrido por Manta Bay, Crystal Bay y Gamat Bay',
                            'Oportunidad de nadar junto a mantarrayas en su hábitat natural',
                            'Equipo completo de snorkel (mascarilla, tubo, aletas)',
                            'Traslado en barco entre los puntos',
                            'Sesión de fotos submarinas con GoPro',
                            'Guía local bilingüe',
                            'Duración: 2–3 horas de snorkel activo',
                        ],
                        'rating' => '4.8', 'cta_label' => 'Asegura Tu Lugar – €13.50', 'cta_url' => $wa . '?text=Quiero%20reservar%20Group%20Saver',
                    ],
                    [
                        'name' => 'Manta Point Sharing', 'ribbon' => 'El Favorito de Todos', 'ribbon_style' => 'coral', 'featured' => false,
                        'icon' => '',
                        'audience'  => 'Parejas, amigos o viajeros individuales que quieren comodidad sin pagar de más.',
                        'price_old' => '', 'price_now' => '€24.50', 'unit' => 'por persona · grupos de 1 a 9 personas',
                        'features'  => [
                            'Visita a Manta Point, Crystal Bay y Gamat Bay',
                            'Más tiempo en cada punto para disfrutar sin prisas',
                            'Equipo de snorkel premium',
                            'Toalla fresca incluida',
                            'Agua mineral ilimitada',
                            'Seguro de viaje básico',
                            'Traslados en barco rápido',
                            'Galería completa de fotos y videos submarinos',
                            'Guía con certificación avanzada',
                            'Duración: 3–5 horas de aventura',
                        ],
                        'rating' => '5.0', 'cta_label' => 'Reserva Tu Experiencia – €24.50', 'cta_url' => $wa . '?text=Quiero%20reservar%20Manta%20Point',
                    ],
                    [
                        'name' => 'Small Group Experience', 'ribbon' => '', 'ribbon_style' => 'accent', 'featured' => false,
                        'icon' => '',
                        'audience'  => 'Parejas románticas, mejores amigos o viajeros solos que valoran la atención personalizada.',
                        'price_old' => '', 'price_now' => '€14.70', 'unit' => 'por persona · 1–4 personas',
                        'features'  => [
                            'Exploración de Manta Bay, Crystal Bay y Gamat Bay',
                            'Equipo de snorkel de alta calidad',
                            'Traslados en barco entre puntos',
                            'Sesión fotográfica submarina incluida',
                            'Guía dedicado para tu pequeño grupo',
                            'Duración: 2–3 horas',
                        ],
                        'rating' => '5.0', 'cta_label' => 'Reserva Tu Small Group – €14.70', 'cta_url' => $wa . '?text=Quiero%20reservar%20Small%20Group',
                    ],
                    [
                        'name' => 'Private Couple Experience', 'ribbon' => '', 'ribbon_style' => 'accent', 'featured' => false,
                        'icon' => 'heart',
                        'audience'  => 'Lunas de miel, aniversarios o parejas que buscan privacidad total.',
                        'price_old' => '', 'price_now' => '€83.30', 'unit' => 'por pareja (máximo 2 personas) · €41.65 por persona',
                        'features'  => [
                            'Recorrido exclusivo por Manta Bay, Crystal Bay y Gamat Bay',
                            'Equipo de snorkel completo',
                            'Barco privado solo para ustedes',
                            'Fotógrafo submarino personal',
                            'Guía dedicado',
                            'Duración: 2–3 horas',
                            'Horarios flexibles según tu preferencia',
                        ],
                        'rating' => '4.9', 'cta_label' => 'Reserva Tu Experiencia Privada – €83.30', 'cta_url' => $wa . '?text=Quiero%20reservar%20Private%20Couple',
                    ],
                    [
                        'name' => 'Group Private', 'ribbon' => '', 'ribbon_style' => 'accent', 'featured' => false,
                        'icon' => 'users',
                        'audience'  => 'Grupos de amigos o familias que quieren compartir la aventura sin extraños.',
                        'price_old' => '', 'price_now' => '€98.00', 'unit' => 'por grupo (hasta 4 personas) · €24.50 por persona',
                        'features'  => [
                            'Aventura por Manta Bay, Crystal Bay y Gamat Bay',
                            'Equipo de snorkel para todos',
                            'Embarcación privada',
                            'Documentación fotográfica completa',
                            'Guía personal',
                            'Duración: 2–3 horas',
                        ],
                        'rating' => '4.8', 'cta_label' => 'Reserva Tu Grupo Privado – €98.00', 'cta_url' => $wa . '?text=Quiero%20reservar%20Grupo%20Privado',
                    ],
                ],
            ],
            'compare' => [
                'enabled'     => true,
                'heading'     => 'Elige el Barco Adecuado para Ti',
                'col_feature' => 'Característica',
                'col_a'       => 'Barco Compartido',
                'col_b'       => 'Barco Privado (VIP)',
                'rows'        => [
                    ['feature' => 'Capacidad', 'a' => 'Hasta 20 personas', 'b' => 'Exclusivo (solo tú)'],
                    ['feature' => 'Precio', 'a' => 'Más económico (desde €13.50)', 'b' => 'Premium (desde €83.30)'],
                    ['feature' => 'Horario', 'a' => 'Salida fija', 'b' => 'Horario flexible'],
                    ['feature' => 'Experiencia', 'a' => 'Diversión en grupo', 'b' => 'Personal y privado'],
                ],
                'note' => 'Nota: Los barcos compartidos acomodan múltiples grupos. Si prefieres una experiencia privada, elige nuestra opción VIP.',
            ],
            'schedule' => [
                'enabled'    => true,
                'heading'    => 'Horarios Diarios de Salida para Snorkel en Nusa Penida',
                'subheading' => 'Salidas diarias – Llega 30 minutos antes para ajuste de equipo y briefing de seguridad',
                'sessions'   => [
                    ['icon' => 'sunrise', 'title' => 'Salida Matutina – 09:00 AM', 'checkin' => '08:30 AM', 'best_for' => 'Aguas tranquilas y mayores probabilidades de ver mantarrayas', 'recommended' => 'Viajeros que llegan en barco rápido desde Sanur a las 07:30 AM'],
                    ['icon' => 'sun', 'title' => 'Salida Vespertina – 02:00 PM', 'checkin' => '01:30 PM', 'best_for' => 'Viajeros de día que llegan en barcos rápidos matutinos desde Bali', 'recommended' => 'Quienes quieren explorar la isla por la mañana'],
                ],
                'notice_title' => 'Importante',
                'notice_items' => [
                    'Todos los huéspedes deben llegar al punto de encuentro al menos 30 minutos antes de la salida programada.',
                    'Ajuste de Equipo: Necesitamos tiempo para asegurar que tus aletas, mascarilla y tubo queden perfectos.',
                    'Briefing de Seguridad: Nuestros guías te darán información esencial sobre las condiciones actuales y protocolos de seguridad.',
                    'Puntualidad: El barco sale exactamente a la hora programada. Llegadas tarde pueden resultar en perder el viaje sin reembolso.',
                ],
            ],
            'steps' => [
                'enabled' => true,
                'heading' => 'Proceso Simple – Desde la Reserva Hasta el Agua',
                'items'   => [
                    ['icon' => 'device-mobile', 'title' => 'Reserva Online', 'text' => 'Elige tu paquete, selecciona tu fecha, paga de forma segura vía WhatsApp o tarjeta. Toma 2 minutos.'],
                    ['icon' => 'car', 'title' => 'Te Recogemos', 'text' => 'Nuestro conductor te recoge en tu hotel en Bali o en tu alojamiento en Nusa Penida.'],
                    ['icon' => 'swimming', 'title' => 'Equípate y Sumérgete', 'text' => 'Tu guía te ajusta el equipo de snorkel de calidad. Breve briefing de seguridad... ¡y al agua!'],
                    ['icon' => 'camera', 'title' => 'Fotos Entregadas', 'text' => 'Entrega el mismo día de todas tus fotos submarinas directamente a tu WhatsApp. Sin costo extra.'],
                ],
            ],
            'checklist' => [
                'enabled'        => true,
                'heading'        => 'Qué Incluye y Qué No Incluye Tu Tour',
                'subheading'     => 'Desglose claro de lo que está cubierto en tu tour para que no haya sorpresas el día del viaje.',
                'included_title' => 'Qué Está Incluido',
                'included'       => [
                    'Tour de snorkel en barco',
                    'Equipo de snorkel (mascarilla, tubo, aletas)',
                    'Guía profesional certificado',
                    'Documentación con GoPro (fotos y videos)',
                    'Agua mineral',
                    'Seguro de viaje básico',
                ],
                'excluded_title' => 'Qué No Está Incluido',
                'excluded'       => [
                    'Barco rápido puerto Sanur – Nusa Penida (ida y vuelta)',
                    'Transporte desde y hacia el puerto de Sanur',
                    'Transporte desde y hacia el hotel',
                    'Entradas adicionales para huéspedes extranjeros (temporada alta)',
                    'Retrasos causados por el participante',
                    'Medicamentos personales',
                    'Artículos de uso personal',
                ],
            ],
            'packing' => [
                'enabled'    => true,
                'heading'    => 'Qué Llevar a Tu Tour de Snorkel en Nusa Penida',
                'subheading' => 'Empaca de forma inteligente para una aventura sin complicaciones en Nusa Penida.',
                'items'      => [
                    ['icon' => 'sun', 'label' => 'Protector solar (reef-safe)'],
                    ['icon' => 'sunglasses', 'label' => 'Gafas de sol'],
                    ['icon' => 'umbrella', 'label' => 'Sombrero de playa'],
                    ['icon' => 'cookie', 'label' => 'Snacks y suplementos'],
                    ['icon' => 'shirt', 'label' => 'Ropa de repuesto'],
                    ['icon' => 'swimming', 'label' => 'Traje de baño'],
                    ['icon' => 'shoe', 'label' => 'Sandalias para caminar'],
                    ['icon' => 'battery', 'label' => 'Power bank'],
                    ['icon' => 'pill', 'label' => 'Medicamentos personales'],
                    ['icon' => 'bath', 'label' => 'Artículos de aseo'],
                    ['icon' => 'droplet', 'label' => 'Toallitas húmedas'],
                    ['icon' => 'backpack', 'label' => 'Bolsa seca (drybag)'],
                    ['icon' => 'device-mobile', 'label' => 'Tarjeta SIM Telkomsel'],
                    ['icon' => 'cash', 'label' => 'Efectivo adicional'],
                ],
                'tip' => 'Consejo: Empaca ligero pero inteligente. El sol en Nusa Penida es intenso y muchos puntos implican caminar en terreno irregular. Sandalias adecuadas, protector solar y protección a prueba de agua para tu equipo marcan la diferencia.',
            ],
            'testimonials' => [
                'enabled'    => true,
                'heading'    => 'Historias Reales de Viajeros Reales',
                'subheading' => 'No confíes solo en nuestras palabras. Escucha a quienes ya vivieron la experiencia',
                'items'      => [
                    ['quote' => 'Nunca olvidaré el momento en que una mantarraya del tamaño de un auto pasó nadando a menos de un metro de mí. El guía nos explicó todo sobre su comportamiento y nos aseguró en todo momento. Simplemente mágico.', 'author' => 'Andrea P.', 'location' => 'Barcelona, España', 'rating' => '5'],
                    ['quote' => 'Viajé sola desde Ciudad de México y me sentí súper segura. El guía estuvo conmigo todo el tiempo, me enseñó a respirar correctamente y al final vi tres tortugas en Crystal Bay. Vale cada centavo.', 'author' => 'Valeria S.', 'location' => 'Ciudad de México, México', 'rating' => '5'],
                    ['quote' => 'Reservamos el paquete privado para nuestro aniversario y fue la mejor decisión. Sin multitudes, el guía nos llevó a los mejores puntos y las fotos quedaron increíbles. 100% recomendado para parejas.', 'author' => 'Martín y Carolina D.', 'location' => 'Buenos Aires, Argentina', 'rating' => '5'],
                    ['quote' => 'Excelente relación calidad-precio. Comparé con Viator y GetYourGuide y aquí obtienes mucho más por menos dinero. El equipo está en perfecto estado y los guías son súper amables.', 'author' => 'Felipe R.', 'location' => 'Santiago, Chile', 'rating' => '5'],
                    ['quote' => 'Llevé a mis hijos de 8 y 10 años y fue la actividad favorita de todo el viaje a Bali. Los guías fueron pacientes, les enseñaron a usar el equipo y al final los niños no querían salir del agua.', 'author' => 'Patricia M.', 'location' => 'Bogotá, Colombia', 'rating' => '5'],
                ],
            ],
            'faq' => [
                'enabled'    => true,
                'heading'    => 'Preguntas Frecuentes sobre Snorkel en Nusa Penida',
                'subheading' => 'Encuentra respuestas a las preguntas más comunes sobre nuestros tours de snorkel',
                'items'      => [
                    ['question' => '¿Es seguro hacer snorkel en Nusa Penida?', 'answer' => 'Sí, trabajamos solo en zonas seguras con corrientes manejables. Nuestros guías están certificados en Rescue Diver y primeros auxilios. Siempre llevamos chalecos salvavidas y monitoreamos las condiciones del mar en tiempo real.'],
                    ['question' => '¿Necesito experiencia previa en snorkel?', 'answer' => 'No, nuestros tours son perfectos para principiantes. Te damos instrucciones básicas antes de entrar al agua y nuestros guías te acompañan durante toda la experiencia. Ofrecemos dispositivos de flotación si los necesitas.'],
                    ['question' => '¿Qué pasa si no veo mantarrayas?', 'answer' => 'Las mantarrayas son animales salvajes, por lo que no podemos garantizar avistamientos al 100%. Sin embargo, Manta Bay tiene avistamientos durante todo el año y nuestros guías conocen las mejores horas y condiciones. Muchos operadores ofrecen reintentos si las condiciones son muy malas.'],
                    ['question' => '¿Cómo llego al punto de encuentro?', 'answer' => 'Te recogemos en tu hotel en Bali (área de Sanur) o en tu alojamiento en Nusa Penida. Si llegas en barco rápido desde Sanur, te recomendamos tomar el barco de las 07:30 AM para llegar cómodamente a nuestra sesión de las 09:00 AM.'],
                    ['question' => '¿Puedo cancelar o cambiar mi reserva?', 'answer' => 'Sí, ofrecemos cancelación gratis hasta 24 horas antes del tour. Para cambios de fecha, contáctanos por WhatsApp y te ayudamos sin costo adicional (sujeto a disponibilidad).'],
                    ['question' => '¿Los niños pueden participar?', 'answer' => 'Sí, niños desde 4 años pueden participar con supervisión de un adulto. Ofrecemos equipo de snorkel de tamaño infantil y chalecos salvavidas para niños.'],
                    ['question' => '¿Qué tipo de barco utilizan?', 'answer' => 'Utilizamos lanchas rápidas modernas con capacidad para 20 personas máximo. Todos nuestros barcos tienen chalecos salvavidas, botiquines de primeros auxilios y sistemas de comunicación para monitoreo del clima.'],
                    ['question' => '¿Las fotos con GoPro realmente están incluidas?', 'answer' => 'Sí, todas nuestras reservas incluyen fotos y videos submarinos profesionales con GoPro. Te enviamos todo el material a tu WhatsApp el mismo día, sin costo extra.'],
                ],
            ],
            'final_cta' => [
                'enabled'             => true,
                'heading'             => 'Tu Aventura Submarina Te Está Esperando',
                'subheading'          => 'Las plazas son limitadas y se agotan rápido, especialmente en temporada alta (junio–agosto y diciembre–enero). No te quedes fuera.',
                'urgency'             => '22 personas reservaron en las últimas 72 horas',
                'cta_primary_label'   => 'Sí, Quiero Reservar Mi Tour de Snorkel',
                'cta_primary_url'     => '#pricing',
                'cta_secondary_label' => 'Prefiero Chatear por WhatsApp – Respuesta Inmediata',
                'cta_secondary_url'   => $wa,
                'whatsapp_label'      => 'WhatsApp: ' . whatsapp_number(),
                'whatsapp_url'        => $wa,
                'hours'               => 'Atención: Todos los días, 07:00 – 20:00',
            ],
            'blog' => [
                'heading'          => 'Blog de Snorkel en Nusa Penida',
                'intro'            => 'Guías, consejos y novedades para planear tu aventura de snorkel en Bali y Nusa Penida.',
                'meta_title'       => 'Blog | Consejos de Snorkel en Nusa Penida',
                'meta_description' => 'Guías prácticas, temporadas, puntos de snorkel y consejos de viaje para Nusa Penida, escritos por guías locales.',
            ],
            'single_cta' => [
                'enabled'   => true,
                'heading'   => '¿Listo para vivirlo en persona?',
                'text'      => 'Reserva tu tour de snorkel en Nusa Penida con guías locales certificados. Fotos GoPro incluidas y cancelación gratis.',
                'cta_label' => 'Ver paquetes y precios',
                'cta_url'   => '/#pricing',
            ],
        ];
    }

    /**
     * Saved document merged over the defaults (memoised per request).
     */
    public static function get(): array
    {
        if (static::$loaded !== null) {
            return static::$loaded;
        }

        $raw   = (new SettingModel())->get(self::SETTING_KEY);
        $saved = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return static::$loaded = is_array($saved)
            ? static::merge(static::defaults(), $saved)
            : static::defaults();
    }

    public static function save(array $content): void
    {
        (new SettingModel())->setValue(
            self::SETTING_KEY,
            json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            false,
        );

        static::$loaded = null;

        // The front page, blog listing and single posts are all guest-cached
        // and all embed this content, so drop everything rather than track
        // every key that might include it.
        Services::cacheManager()->flushAll();
    }

    /**
     * Normalise a submitted admin form into the schema's shape: trims
     * strings, splits "one per line" textareas, turns checkbox presence
     * into booleans and drops repeater rows that are entirely blank.
     */
    public static function fromPost(array $post): array
    {
        $out = [];

        foreach (static::schema() as $section => $fields) {
            $input         = is_array($post[$section] ?? null) ? $post[$section] : [];
            $out[$section] = static::normalise($fields, $input);
        }

        return $out;
    }

    protected static function normalise(array $fields, array $input): array
    {
        $row = [];

        foreach ($fields as $key => $type) {
            $value = $input[$key] ?? null;

            if (is_array($type)) { // repeater
                $items = [];

                foreach (is_array($value) ? $value : [] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $clean = static::normalise($type['list'], $item);

                    // Keep the row only if some text field carries a value.
                    $hasText = false;
                    foreach ($type['list'] as $subKey => $subType) {
                        if ($subType === 'bool') {
                            continue;
                        }
                        if (($subType === 'lines' && $clean[$subKey] !== []) || ($subType === 'text' && $clean[$subKey] !== '')) {
                            $hasText = true;
                            break;
                        }
                    }

                    if ($hasText) {
                        $items[] = $clean;
                    }
                }

                $row[$key] = $items;
                continue;
            }

            $row[$key] = match ($type) {
                'bool'  => $value !== null && $value !== '' && $value !== '0',
                'lines' => static::lines(is_string($value) ? $value : ''),
                default => trim(is_string($value) ? $value : ''),
            };
        }

        return $row;
    }

    protected static function lines(string $text): array
    {
        $parts = preg_split('/\R/u', $text) ?: [];
        $parts = array_map('trim', $parts);

        return array_values(array_filter($parts, static fn (string $line) => $line !== ''));
    }

    /**
     * Defaults ← saved. Objects merge per key; lists and scalars from the
     * saved side win outright. Unknown saved keys are ignored.
     */
    protected static function merge(array $defaults, array $saved): array
    {
        foreach ($defaults as $key => $default) {
            if (! array_key_exists($key, $saved)) {
                continue;
            }

            $value = $saved[$key];

            if (is_array($default) && static::isAssoc($default) && is_array($value)) {
                $defaults[$key] = static::merge($default, $value);
            } elseif (is_array($default) && ! static::isAssoc($default)) {
                $defaults[$key] = is_array($value) ? array_values($value) : $default;
            } elseif (is_bool($default)) {
                $defaults[$key] = (bool) $value;
            } elseif (! is_array($value)) {
                $defaults[$key] = (string) $value;
            }
        }

        return $defaults;
    }

    protected static function isAssoc(array $array): bool
    {
        return $array !== [] && array_keys($array) !== range(0, count($array) - 1);
    }
}
