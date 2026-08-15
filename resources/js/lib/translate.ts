import { usePage } from '@inertiajs/react'

/**
 * Traducciones, con la misma forma que en el servidor.
 *
 * Las claves vienen de lang/es/*.php como props compartidas. Aqui no se
 * escribe ni un texto: si una cadena no existe, se devuelve la clave para
 * que el hueco se vea en pantalla en lugar de mostrar una cadena vacia que
 * nadie relaciona con nada.
 */
type Translations = Record<string, unknown>

interface SharedProps {
    translations: Translations
    locale: string
    dir: 'ltr' | 'rtl'
    [key: string]: unknown
}

function walk(source: unknown, path: string[]): unknown {
    return path.reduce<unknown>((current, segment) => {
        if (current && typeof current === 'object' && segment in current) {
            return (current as Record<string, unknown>)[segment]
        }

        return undefined
    }, source)
}

export function useTranslate() {
    const { translations } = usePage<SharedProps>().props

    return (key: string, replace: Record<string, string | number> = {}): string => {
        const value = walk(translations, key.split('.'))

        if (typeof value !== 'string') {
            return key
        }

        return Object.entries(replace).reduce(
            (text, [token, replacement]) => text.replaceAll(`:${token}`, String(replacement)),
            value,
        )
    }
}
