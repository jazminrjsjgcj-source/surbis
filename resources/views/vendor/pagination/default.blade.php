{{--
    Paginacion.

    Propia y no la que publica Laravel: la suya trae utilidades de Tailwind
    escritas a mano, con colores fuera de nuestros tokens y direcciones
    fijas. Adaptarla cuesta mas que escribirla.
--}}
@if ($paginator->hasPages())
    <nav class="pagination" aria-label="{{ __('interface.pagination.label') }}">
        <p>
            {!! __('interface.pagination.showing', [
                'first' => $paginator->firstItem(),
                'last' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]) !!}
        </p>

        <div class="pagination-links">
            @if ($paginator->onFirstPage())
                <span class="pagination-link" aria-disabled="true">
                    {{ __('interface.pagination.previous') }}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pagination-link" rel="prev">
                    {{ __('interface.pagination.previous') }}
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination-link" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination-link" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="pagination-link">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pagination-link" rel="next">
                    {{ __('interface.pagination.next') }}
                </a>
            @else
                <span class="pagination-link" aria-disabled="true">
                    {{ __('interface.pagination.next') }}
                </span>
            @endif
        </div>
    </nav>
@endif
