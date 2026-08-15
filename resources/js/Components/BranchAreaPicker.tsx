import { useTranslate } from '@/lib/translate'

export interface BranchOption {
    id: number
    name: string
    areas: { id: number; name: string }[]
}

interface Props {
    branches: BranchOption[]
    branchId: number | null
    areaId: number | null
    idPrefix: string
    disabled?: boolean
    onChange: (branchId: number | null, areaId: number | null) => void
}

/**
 * Sucursal y area, encadenadas.
 *
 * Las areas dependen de la sucursal elegida. Al cambiar de sucursal el area
 * se limpia SIEMPRE, porque un area de otra sede no tiene sentido y el
 * servidor la rechazaria: dejarla puesta produciria un error de validacion
 * que la persona no relacionaria con haber cambiado la sucursal.
 */
export default function BranchAreaPicker({
    branches,
    branchId,
    areaId,
    idPrefix,
    disabled = false,
    onChange,
}: Props) {
    const t = useTranslate()
    const seleccionada = branches.find((branch) => branch.id === branchId)
    const areas = seleccionada?.areas ?? []

    return (
        <>
            <div className="field">
                <label htmlFor={`${idPrefix}-branch`}>{t('interface.people.branch')}</label>
                <select
                    id={`${idPrefix}-branch`}
                    className="input"
                    value={branchId ?? ''}
                    disabled={disabled}
                    onChange={(e) => {
                        const valor = e.target.value === '' ? null : Number(e.target.value)
                        onChange(valor, null)
                    }}
                >
                    <option value="">{t('interface.people.branch_none')}</option>
                    {branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>
                            {branch.name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="field">
                <label htmlFor={`${idPrefix}-area`}>{t('interface.people.area')}</label>
                <select
                    id={`${idPrefix}-area`}
                    className="input"
                    value={areaId ?? ''}
                    // Sin sucursal no hay areas que elegir. Deshabilitarlo
                    // dice por que esta vacio; dejarlo activo y vacio, no.
                    disabled={disabled || branchId === null}
                    onChange={(e) =>
                        onChange(branchId, e.target.value === '' ? null : Number(e.target.value))
                    }
                >
                    <option value="">{t('interface.people.area_none')}</option>
                    {areas.map((area) => (
                        <option key={area.id} value={area.id}>
                            {area.name}
                        </option>
                    ))}
                </select>
            </div>
        </>
    )
}
