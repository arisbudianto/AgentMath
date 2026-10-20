@if($paginator->hasPages())
<nav class="pagination" aria-label="Navigasi halaman">
<p>Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}</p>
<div class="actions">
@if($paginator->onFirstPage())<span class="muted">Sebelumnya</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif
<span>Halaman {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
@if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>@else<span class="muted">Berikutnya</span>@endif
</div></nav>
@endif
