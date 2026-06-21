import mountIsland from '@/lib/mountIsland';
import SessionTimer from './SessionTimer.vue';

const lifetime = Number(document.querySelector('meta[name="session-lifetime"]')?.content);
const loginUrl = document.querySelector('meta[name="login-url"]')?.content || '/login';
mountIsland('session-timer', SessionTimer, { lifetime, loginUrl });
