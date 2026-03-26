<?php
/**
 * Componente Preguntas Frecuentes - Acorde├│n con HTML5 details/summary
 * Variables globales disponibles: $user, app_base_url()
 */
?>
    <!-- Preguntas Frecuentes (Acorde├│n) -->
    <section id="faq" class="py-16 md:py-24 bg-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-primary-700 mb-4">
                    Preguntas Frecuentes
                </h2>
                <p class="text-lg md:text-xl text-gray-600 max-w-2xl mx-auto">
                    Todo lo que necesitas saber sobre La Estaci├│n del Domin├│
                </p>
            </div>
            
            <div class="max-w-4xl mx-auto space-y-4">
                <details class="group bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition-all border border-gray-200">
                    <summary class="flex items-center justify-between cursor-pointer font-bold text-lg text-gray-900 list-none">
                        <span class="flex items-center">
                            <i class="fas fa-question-circle text-primary-500 mr-3"></i>
                            ┬┐C├│mo me registro en la plataforma?
                        </span>
                        <i class="fas fa-chevron-down text-primary-500 group-open:rotate-180 transition-transform"></i>
                    </summary>
                    <p class="mt-4 text-gray-600 pl-10 leading-relaxed">
                        Es muy f├ícil. Haz clic en el bot├│n "Registrarme" en la parte superior, elige si quieres registrarte como jugador o solicitar afiliaci├│n para tu club, completa el formulario con tus datos y ┬ílisto! Podr├ís acceder a todas las funcionalidades de la plataforma.
                    </p>
                </details>
                
                <details class="group bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition-all border border-gray-200">
                    <summary class="flex items-center justify-between cursor-pointer font-bold text-lg text-gray-900 list-none">
                        <span class="flex items-center">
                            <i class="fas fa-question-circle text-primary-500 mr-3"></i>
                            ┬┐Es gratuito participar en los torneos?
                        </span>
                        <i class="fas fa-chevron-down text-primary-500 group-open:rotate-180 transition-transform"></i>
                    </summary>
                    <p class="mt-4 text-gray-600 pl-10 leading-relaxed">
                        Depende del torneo. Algunos son completamente gratuitos, mientras que otros tienen un costo de inscripci├│n. Toda la informaci├│n sobre costos, fechas y modalidades est├í disponible en la ficha de cada torneo antes de inscribirte.
                    </p>
                </details>
                
                <details class="group bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition-all border border-gray-200">
                    <summary class="flex items-center justify-between cursor-pointer font-bold text-lg text-gray-900 list-none">
                        <span class="flex items-center">
                            <i class="fas fa-question-circle text-primary-500 mr-3"></i>
                            ┬┐C├│mo puedo contactar a un club?
                        </span>
                        <i class="fas fa-chevron-down text-primary-500 group-open:rotate-180 transition-transform"></i>
                    </summary>
                    <p class="mt-4 text-gray-600 pl-10 leading-relaxed">
                        En la informaci├│n de cada club encontrar├ís los datos de contacto (tel├®fono, email, direcci├│n) para comunicarte directamente con sus responsables. Tambi├®n puedes usar el sistema de invitaciones para recibir informaci├│n directamente del club.
                    </p>
                </details>
                
                <details class="group bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition-all border border-gray-200">
                    <summary class="flex items-center justify-between cursor-pointer font-bold text-lg text-gray-900 list-none">
                        <span class="flex items-center">
                            <i class="fas fa-question-circle text-primary-500 mr-3"></i>
                            ┬┐Puedo ver los resultados de torneos anteriores?
                        </span>
                        <i class="fas fa-chevron-down text-primary-500 group-open:rotate-180 transition-transform"></i>
                    </summary>
                    <p class="mt-4 text-gray-600 pl-10 leading-relaxed">
                        ┬íPor supuesto! Todos los resultados de torneos finalizados est├ín disponibles en la secci├│n "Eventos Realizados". Puedes consultar clasificaciones, estad├¡sticas, fotograf├¡as y m├ís informaci├│n de cada evento.
                    </p>
                </details>
                
                <details class="group bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition-all border border-gray-200">
                    <summary class="flex items-center justify-between cursor-pointer font-bold text-lg text-gray-900 list-none">
                        <span class="flex items-center">
                            <i class="fas fa-question-circle text-primary-500 mr-3"></i>
                            ┬┐C├│mo puedo organizar un torneo?
                        </span>
                        <i class="fas fa-chevron-down text-primary-500 group-open:rotate-180 transition-transform"></i>
                    </summary>
                    <p class="mt-4 text-gray-600 pl-10 leading-relaxed">
                        Si eres administrador de un club, puedes crear y gestionar torneos directamente desde tu panel. Si a├║n no tienes un club registrado, puedes solicitar la afiliaci├│n de tu club desde la secci├│n de registro. Una vez aprobado, tendr├ís acceso completo a las herramientas de gesti├│n de torneos.
                    </p>
                </details>
                
                <details class="group bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition-all border border-gray-200">
                    <summary class="flex items-center justify-between cursor-pointer font-bold text-lg text-gray-900 list-none">
                        <span class="flex items-center">
                            <i class="fas fa-question-circle text-primary-500 mr-3"></i>
                            ┬┐Necesito estar afiliado a un club para participar?
                        </span>
                        <i class="fas fa-chevron-down text-primary-500 group-open:rotate-180 transition-transform"></i>
                    </summary>
                    <p class="mt-4 text-gray-600 pl-10 leading-relaxed">
                        No es obligatorio. Puedes registrarte como jugador independiente y participar en cualquier torneo que est├® abierto. Sin embargo, algunos torneos pueden tener restricciones espec├¡ficas que se indican en su informaci├│n.
                    </p>
                </details>
            </div>
            
            <div class="text-center mt-12">
                <p class="text-gray-600 mb-4">┬┐Tienes m├ís preguntas?</p>
                <a href="#comentarios" class="inline-block bg-primary-500 text-white px-8 py-3 rounded-lg font-semibold hover:bg-primary-600 transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-comments mr-2"></i>Env├¡anos tu Consulta
                </a>
            </div>
        </div>
    </section>
