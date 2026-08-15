import { Link, useForm } from '@inertiajs/react'
import type { FormEvent } from 'react'

import ErrorSummary from '@/Components/ErrorSummary'
import StatusMessage from '@/Components/StatusMessage'
import AuthShell from '@/Layouts/AuthShell'
import { useTranslate } from '@/lib/translate'

interface Props {
    forgotUrl: string
}

export default function Login({ forgotUrl }: Props) {
    const t = useTranslate()

    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    })

    function submit(event: FormEvent) {
        event.preventDefault()

        // La contrasena no se conserva al fallar: solo el correo. Rellenar de
        // nuevo el correo es trabajo que el sistema puede evitar; conservar la
        // contrasena en memoria del navegador, no.
        post('/login', { onFinish: () => setData('password', '') })
    }

    return (
        <AuthShell title={t('interface.login.title')} subtitle={t('interface.login.subtitle')}>
            <h1 className="text-xl">{t('interface.login.heading')}</h1>
            <p className="hint mt-1 mb-4">{t('interface.login.help')}</p>

            <StatusMessage />
            <ErrorSummary errors={errors} />

            <form onSubmit={submit}>
                <div className="field">
                    <label htmlFor="email">{t('interface.login.email')}</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        className="input"
                        value={data.email}
                        onChange={(event) => setData('email', event.target.value)}
                        autoComplete="username"
                        inputMode="email"
                        required
                        aria-invalid={errors.email ? true : undefined}
                        aria-describedby={errors.email ? 'email-error' : undefined}
                    />
                    {errors.email && (
                        <span id="email-error" className="error">
                            {errors.email}
                        </span>
                    )}
                </div>

                <div className="field">
                    <label htmlFor="password">{t('interface.login.password')}</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        className="input"
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                        autoComplete="current-password"
                        required
                    />
                </div>

                {/* flex-wrap: a 320 px los elementos se apilan en lugar de
                    desbordar. RNF-GEN-007. */}
                <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <label htmlFor="remember" className="text-ink-muted flex items-center gap-2 text-sm">
                        <input
                            id="remember"
                            name="remember"
                            type="checkbox"
                            checked={data.remember}
                            onChange={(event) => setData('remember', event.target.checked)}
                        />
                        {t('interface.login.remember')}
                    </label>

                    <Link href={forgotUrl} className="text-primary text-sm">
                        {t('interface.login.forgot')}
                    </Link>
                </div>

                <button type="submit" className="btn btn-primary btn-lg btn-block" disabled={processing}>
                    {t('interface.login.submit')}
                </button>
            </form>
        </AuthShell>
    )
}
