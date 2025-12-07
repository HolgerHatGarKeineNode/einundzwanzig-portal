<?php

namespace App\Enums;

enum SelfHostedServiceType: string
{
    case Alby = 'alby';
    case BtcpayServer = 'btcpay_server';
    case ElectrumFulcrumServer = 'electrum_fulcrum_server';
    case LNbits = 'lnbits';
    case LnbitsServer = 'lnbits_server';
    case Mempool = 'mempool';
    case NostrBlossomServer = 'nostr_blossom_server';
    case NostrClient = 'nostr_client';
    case NostrRelayServer = 'nostr_relay_server';
    case PkarrDnsServer = 'pkarr_dns_server';
    case Other = 'other';

    public function color(): string
    {
        return match ($this) {
            self::Mempool => 'blue',
            self::LNbits => 'purple',
            self::Alby => 'amber',
            self::ElectrumFulcrumServer => 'cyan',
            self::BtcpayServer => 'green',
            self::LnbitsServer => 'violet',
            self::NostrRelayServer => 'fuchsia',
            self::NostrClient => 'pink',
            self::NostrBlossomServer => 'rose',
            self::PkarrDnsServer => 'orange',
            self::Other => 'zinc',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Mempool => 'Mempool',
            self::LNbits => 'LNbits',
            self::Alby => 'Alby',
            self::ElectrumFulcrumServer => 'Electrum/Fulcrum Server',
            self::BtcpayServer => 'BTCPay Server',
            self::LnbitsServer => 'LNbits Server',
            self::NostrRelayServer => 'Nostr Relay',
            self::NostrClient => 'Nostr Client',
            self::NostrBlossomServer => 'Nostr Blossom',
            self::PkarrDnsServer => 'Pkarr DNS Server',
            self::Other => 'Other',
        };
    }
}
