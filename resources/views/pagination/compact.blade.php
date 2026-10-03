@if ($paginator->hasPages())
    @once
        <style>
            .mci-pagination{box-sizing:border-box;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;width:100%;max-width:100%;margin:20px 0;padding:0;background:#fff;border:0;box-shadow:none;position:static;font:14px/1.4 system-ui,-apple-system,"Segoe UI",sans-serif;color:#334155}
            .mci-pagination *{box-sizing:border-box}
            .mci-pagination .mci-pagination-summary{margin:0;font-size:13px;color:#475569}
            .mci-pagination .mci-pagination-links{display:flex;flex-wrap:wrap;align-items:center;gap:6px;max-width:100%;margin:0;padding:0;list-style:none}
            .mci-pagination .mci-pagination-links>li{display:block;flex:0 0 auto;margin:0;padding:0}
            .mci-pagination .mci-page-link{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-width:40px;min-height:40px;width:auto;height:auto;max-width:100%;margin:0;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#163b67;font:600 14px/1.4 system-ui,-apple-system,"Segoe UI",sans-serif;text-decoration:none;white-space:nowrap;box-shadow:none}
            .mci-pagination a.mci-page-link:hover{background:#eef4fc;border-color:#164e8a}
            .mci-pagination a.mci-page-link:focus-visible{outline:3px solid #2563eb;outline-offset:2px}
            .mci-pagination .mci-page-link[aria-current="page"]{background:#163b67;border-color:#163b67;color:#fff}
            .mci-pagination .mci-page-link[aria-disabled="true"]{background:#f1f5f9;color:#64748b;cursor:default}
            .mci-pagination .mci-page-arrow{display:inline-block;flex:0 0 16px;width:16px;height:20px;font:700 20px/18px system-ui,sans-serif;text-align:center}
            .mci-pagination .mci-page-ellipsis{display:inline-block;padding:8px 4px;color:#64748b}
            @media(max-width:640px){.mci-pagination{justify-content:center;gap:10px}.mci-pagination .mci-pagination-summary{flex-basis:100%;text-align:center}.mci-pagination .mci-pagination-links{justify-content:center}.mci-pagination .mci-page-number,.mci-pagination .mci-page-gap{display:none}.mci-pagination .mci-page-link{min-height:44px}}
        </style>
    @endonce

    <nav class="mci-pagination" aria-label="{{ __('Pagination Navigation') }}">
        @if (method_exists($paginator, 'total'))
            <p class="mci-pagination-summary">
                {{ __('Page') }} {{ $paginator->currentPage() }} {{ __('of') }} {{ $paginator->lastPage() }}
                <span aria-hidden="true"> &middot; </span>
                {{ number_format($paginator->total()) }} {{ __('results') }}
            </p>
        @elseif (method_exists($paginator, 'currentPage'))
            <p class="mci-pagination-summary">{{ __('Page') }} {{ $paginator->currentPage() }}</p>
        @endif

        <ul class="mci-pagination-links">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="mci-page-link" aria-disabled="true"><span class="mci-page-arrow" aria-hidden="true">&lsaquo;</span>{{ __('Previous') }}</span>
                @else
                    <a class="mci-page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev"><span class="mci-page-arrow" aria-hidden="true">&lsaquo;</span>{{ __('Previous') }}</a>
                @endif
            </li>

            @if (($showPageNumbers ?? false) && isset($elements))
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li class="mci-page-gap"><span class="mci-page-ellipsis" aria-hidden="true">{{ $element }}</span></li>
                    @elseif (is_array($element))
                        @foreach ($element as $page => $url)
                            <li class="mci-page-number">
                                @if ($page == $paginator->currentPage())
                                    <span class="mci-page-link" aria-current="page" aria-label="{{ __('Page :page', ['page' => $page]) }}">{{ $page }}</span>
                                @else
                                    <a class="mci-page-link" href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach
            @endif

            <li>
                @if ($paginator->hasMorePages())
                    <a class="mci-page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Next') }}<span class="mci-page-arrow" aria-hidden="true">&rsaquo;</span></a>
                @else
                    <span class="mci-page-link" aria-disabled="true">{{ __('Next') }}<span class="mci-page-arrow" aria-hidden="true">&rsaquo;</span></span>
                @endif
            </li>
        </ul>
    </nav>
@endif
