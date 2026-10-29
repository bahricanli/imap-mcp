@extends('layouts.app', ['title' => 'Bağlı uygulamalar · Mail MCP'])

@section('content')
    <section class="card">
        <h1>Bağlı uygulamalar</h1>
        <p class="muted">OAuth ile posta hesaplarına erişim izni verdiğin istemciler (Claude, Claude Code…). Bağlantıyı kesince uygulamanın tüm token'ları iptal edilir.</p>

        @if ($connections->isEmpty())
            <p class="muted" style="margin:0">Henüz bağlı uygulama yok.</p>
        @else
            <ul class="list">
                @foreach ($connections as $c)
                    <li>
                        <div class="grow">
                            <div class="title">{{ $c['client']?->name ?? 'Bilinmeyen istemci' }}</div>
                            <div class="sub">
                                İlk bağlantı {{ $c['connected']?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                                · son token {{ $c['last_used']?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                            </div>
                        </div>
                        @if ($c['client'])
                            <form method="POST" action="{{ route('connections.destroy', $c['client']->getKey()) }}"
                                  onsubmit="return confirm('{{ $c['client']->name }} bağlantısı kesilsin mi?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="danger" style="margin:0">Bağlantıyı kes</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
