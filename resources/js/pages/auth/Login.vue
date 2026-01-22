<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

const animateSections = ref(false);
onMounted(() => {
    setTimeout(() => {
        animateSections.value = true;
    }, 30);
});
</script>

<template>
    <AuthBase>

        <Head title="Login PLD - Tláloc Seguros" />
        <Form v-bind="store.form()" :reset-on-success="['password']" v-slot="{ errors, processing }"
            class="flex flex-col gap-6 bg-gray-50/90 rounded-xl shadow-lg px-6 py-8 border border-gray-200 max-w-md md:max-w-2xl lg:max-w-3xl mx-auto w-full transition-all opacity-0 translate-y-6 duration-700 ease-in-out"
            :class="{ 'opacity-100 translate-y-0': animateSections }">
            <!-- Logo y título -->
            <div class="flex flex-col items-center justify-center mb-6 opacity-0 translate-y-6 transition-all duration-700 delay-100 ease-in-out"
                :class="{ 'opacity-100 translate-y-0': animateSections }">
                <div class="relative group">
                    <img src="https://tlalocseguros.com/img/logos/logo.png" alt="Tlaloc Seguros Logo"
                        class="h-20 w-auto drop-shadow-lg mb-4 animate-fade-in-slow" loading="lazy" />
                    <span
                        class="absolute right-2 bottom-1 text-[10px] text-blue-900/10 font-mono pointer-events-none select-none group-hover:opacity-30 opacity-0 transition-all duration-300 ease-in-out"
                        aria-hidden="true">
                        TLSC.PLD
                    </span>
                </div>
                <div class="text-gray-700 text-base text-center font-medium tracking-normal mb-2 relative">
                    Sistema de Prevención de Lavado de Dinero
                </div>
            </div>

            <!-- Mensaje de estado -->
            <div v-if="status"
                class="mb-4 text-center rounded px-4 py-2 bg-green-100 text-green-700 font-medium border border-green-300 shadow opacity-0 translate-y-6 transition-all duration-700 delay-200 ease-in-out"
                :class="{ 'opacity-100 translate-y-0': animateSections }">
                {{ status }}
            </div>

            <div class="flex flex-col gap-5 opacity-0 translate-y-6 transition-all duration-700 delay-300 ease-in-out"
                :class="{ 'opacity-100 translate-y-0': animateSections }">
                <!-- Usuario -->
                <div class="flex flex-col gap-2 opacity-0 translate-y-3 transition-all duration-700 delay-400 ease-in-out"
                    :class="{ 'opacity-100 translate-y-0': animateSections }">
                    <Label for="usuario" class="font-semibold text-gray-800">Usuario</Label>
                    <input id="usuario" type="text" name="usuario" autofocus tabindex="1" autocomplete="username"
                        placeholder="ejemplo"
                        class="border border-gray-300 rounded-md px-3 py-2 transition focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-800 bg-white w-full"
                        spellcheck="false" aria-label="Usuario" />
                    <InputError :message="errors.usuario" />
                </div>

                <!-- Password -->
                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="font-semibold text-gray-800">Contraseña</Label>
                        <TextLink v-if="canResetPassword" :href="request()"
                            class="text-xs underline decoration-blue-500" :tabindex="5">
                            ¿Olvidaste la contraseña?
                        </TextLink>
                    </div>
                    <div class="relative">
                        <input :id="'password'" :type="showPassword ? 'text' : 'password'" name="password" tabindex="2"
                            autocomplete="current-password" placeholder="••••••••"
                            class="border border-gray-300 rounded-md px-3 py-2 pr-10 transition focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-800 bg-white w-full"
                            aria-label="Contraseña" />
                        <button type="button" aria-label="Mostrar/Ocultar contraseña"
                            class="absolute right-2 top-2.5 text-gray-700 hover:text-blue-500 transition-colors"
                            tabindex="-1" @click="toggleShowPassword">
                            <component :is="showPassword ? EyeOff : Eye" class="w-5 h-5" />
                        </button>
                    </div>
                    <InputError :message="errors.password" />
                </div>

                <!-- Recordarme -->
                <div class="flex items-center gap-2 mt-4">
                    <Checkbox id="remember" name="remember" tabindex="3"
                        class="border-blue-500 focus:ring-blue-500 accent-blue-500" />
                    <Label for="remember" class="cursor-pointer font-normal text-gray-800">
                        Recordarme
                    </Label>
                </div>

                <!-- Botón -->
                <Button type="submit"
                    class="mt-6 w-full h-12 font-bold bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 transition-all transition-colors tracking-normal text-base"
                    tabindex="4" :disabled="processing" data-test="login-button">
                    <span v-if="!processing">Ingresar</span>
                    <span v-else>
                        <svg class="animate-spin mx-auto inline-block mr-2 h-5 w-5 text-white"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        Validando…
                    </span>
                </Button>
            </div>

            <!-- Footer -->
            <div class="mt-8 text-xs text-center text-gray-600">
                © {{ new Date().getFullYear() }} Tláloc Seguros. PLD Agropecuario.<br />
            </div>
        </Form>
    </AuthBase>
</template>
