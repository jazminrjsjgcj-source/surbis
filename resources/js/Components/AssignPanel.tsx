import { router } from '@inertiajs/react'
import { useState } from 'react'

import { type BranchOption } from '@/Components/BranchAreaPicker'
import { useTranslate } from '@/lib/translate'

interface Row {
    key: string
    name: string
    branch_id: number | null
    area_id: number | null
    assign_url: string | null
}

/**
 * Asignar sucursal y area a una persona con cuenta.
 *
 * Se abre bajo la tabla y no en un dialogo: con un dialogo habria que
 * resolver foco, tecla de escape y anuncio a lectores de pantalla para una
 * operacion de dos desplegables. Asi se resuelve solo.
 */
export default function AssignPanel({
    row,
    branches,
    onClose,
}: {
    row: Row
    branches: BranchOption[]
    onClose: () => void
}) {
    const t = useTranslate()
    const [branchId, setBranchId] = useState<number | null>(row.branch_id)
    const [areaId, setAreaId] = useState<number | null>(row.area_id)

    const seleccionada = branches.find((branch) => branch.id === branchId)

    return (
        <div className="card card-pad mt-4 max-w-140">
            <h2 className="text-lg">{t('interface.people.assign')}</h2>
            <p className="hint mt-1 mb-3">{row.name}</p>

            <div className="field">
                <label htmlFor="assign-branch">{t('interface.people.branch')}</label>
                <select
                    id="assign-branch"
                    className="input"
                    value={branchId ?? ''}
                    onChange={(e) => {
                        setBranchId(e.target.value === '' ? null : Number(e.target.value))
                        setAreaId(null)
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
                <label htmlFor="assign-area">{t('interface.people.area')}</label>
                <select
                    id="assign-area"
                    className="input"
                    value={areaId ?? ''}
                    disabled={branchId === null}
                    onChange={(e) => setAreaId(e.target.value === '' ? null : Number(e.target.value))}
                >
                    <option value="">{t('interface.people.area_none')}</option>
                    {(seleccionada?.areas ?? []).map((area) => (
                        <option key={area.id} value={area.id}>
                            {area.name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="actions">
                <button
                    type="button"
                    className="btn btn-primary"
                    onClick={() =>
                        router.post(
                            row.assign_url!,
                            { branch_id: branchId, area_id: areaId },
                            { preserveScroll: true, onSuccess: onClose },
                        )
                    }
                >
                    {t('interface.people.save')}
                </button>

                <button type="button" className="btn btn-ghost" onClick={onClose}>
                    {t('interface.people.cancel')}
                </button>
            </div>
        </div>
    )
}
