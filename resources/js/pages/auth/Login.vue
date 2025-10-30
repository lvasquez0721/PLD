<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const showPassword = ref(false);

function toggleShowPassword() {
    showPassword.value = !showPassword.value;
}

// Animación global: para controlar animaciones con delays escalonados
const animateSections = ref(false);

onMounted(() => {
    setTimeout(() => {
        animateSections.value = true;
    }, 30); // leve delay para disparar transición
});
</script>

<template>
    <AuthBase>

        <Head title="Login PLD - Tláloc Seguros" />
        <Form v-bind="store.form()" :reset-on-success="['password']" v-slot="{ errors, processing }" class="flex flex-col gap-6 bg-white/70 rounded-xl shadow-xl px-6 py-8 border border-gray-100 max-w-md md:max-w-2xl lg:max-w-3xl mx-auto w-full transition-all
                opacity-0 translate-y-6
                duration-700
                " :class="{
                    'opacity-100 translate-y-0': animateSections,
                }">
            <!-- Icono y título dentro del card -->
            <div class="flex flex-col items-center justify-center mb-4 mt-2 opacity-0 translate-y-6 transition-all duration-700 delay-100"
                :class="{ 'opacity-100 translate-y-0': animateSections }">
                <div class="relative group">
                    <img src="https://tlalocseguros.com/img/logos/logo.png" alt="Tlaloc Seguros Logo"
                        class="h-20 w-auto drop-shadow-lg mb-2 animate-fade-in-slow" loading="lazy" />
                    <span
                        class="absolute right-2 bottom-1 text-[10px] text-blue-900/10 font-mono pointer-events-none select-none group-hover:opacity-30 opacity-0 transition-all duration-300"
                        aria-hidden="true">
                        TLSC.PLD
                    </span>
                </div>

                <div class="text-gray-700 text-base text-center font-medium tracking-wide mb-2 relative">
                    Sistema de Prevención de Lavado de Dinero
                    <!-- <span
                        class="absolute right-2 bottom-0 text-[10px] text-gray-300 select-none pointer-events-none opacity-70 hidden md:inline-block"
                        aria-hidden="true">
                        PLD–TLSC v6.1
                    </span> -->
                </div>
            </div>

            <!-- Mensaje de status o alerta -->
            <div v-if="status"
                class="mb-4 text-center rounded px-4 py-2 bg-green-100 text-green-700 font-medium border border-green-300 shadow opacity-0 translate-y-6 transition-all duration-700 delay-200"
                :class="{ 'opacity-100 translate-y-0': animateSections }">
                {{ status }}
            </div>

            <div class="flex flex-col gap-5 opacity-0 translate-y-6 transition-all duration-700 delay-300"
                :class="{ 'opacity-100 translate-y-0': animateSections }">
                <!-- Email Field -->
                <div class="flex flex-col gap-2 opacity-0 translate-y-3 transition-all duration-700 delay-400"
                    :class="{ 'opacity-100 translate-y-0': animateSections }">
                    <Label for="email" class="font-semibold text-black">Correo electrónico</Label>
                    <Input id="email" type="email" name="email" autofocus :tabindex="1" autocomplete="email"
                        placeholder="ejemplo@correo.com" value="admin@admin.com"
                        class="transition focus:ring-2 focus:ring-blue-600 focus:border-blue-600 text-black"
                        style="background-color: white !important;"
                        inputmode="email" spellcheck="false" aria-label="Correo electrónico" />
                    <InputError :message="errors.email" />
                </div>
                <!-- Password Field -->
                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="font-semibold text-black">Contraseña</Label>
                        <TextLink v-if="canResetPassword" :href="request()"
                            class="text-xs underline decoration-blue-600" :tabindex="5">
                            ¿Olvidaste la contraseña?
                        </TextLink>
                    </div>
                    <div class="relative">
                        <Input :id="'password'" :type="showPassword ? 'text' : 'password'" name="password"
                            :tabindex="2" autocomplete="current-password" placeholder="••••••••"  value="contraseña123"
                            class="transition focus:ring-2 focus:ring-blue-600 focus:border-blue-600 pr-10 text-black"
                            style="background-color: white !important;"
                            aria-label="Contraseña" />
                        <button type="button" aria-label="Mostrar/Ocultar contraseña"
                            class="absolute right-2 top-2.5 text-black hover:text-blue-600 transition" tabindex="-1"
                            @click="toggleShowPassword">
                            <component :is="showPassword ? EyeOff : Eye" class="w-5 h-5" />
                        </button>
                    </div>
                    <InputError :message="errors.password" />
                </div>
                <!-- Remember Me Checkbox -->
                <div class="flex items-center gap-2 mt-2">
                    <Checkbox id="remember" name="remember" :tabindex="3"
                        class="border-blue-600 focus:ring-blue-600 accent-blue-600" />
                    <Label for="remember" class="cursor-pointer font-normal text-black">Recordarme</Label>
                </div>
                <!-- Submit Button -->
                <Button type="submit"
                    class="mt-2 w-full h-12 font-bold bg-blue-600 text-white rounded-lg shadow hover:bg-blue-700 transition-all tracking-wide text-lg"
                    :tabindex="4" :disabled="processing" data-test="login-button">
                    <span v-if="!processing">Ingresar</span>
                    <span v-else>
                        <svg class="animate-spin mx-auto inline-block mr-2 h-5 w-5 text-white"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
                            </path>
                        </svg>
                        Validando…
                    </span>
                </Button>
            </div>


            <!-- Pie de página extra branding seguridad -->
            <div class="mt-6 text-xs text-center text-gray-600">
                © {{ (new Date()).getFullYear() }} Tláloc Seguros. PLD Agropecuario.<br>
                <span class="inline-flex items-center gap-1">
                </span>
            </div>
        </Form>
    </AuthBase>
</template>
