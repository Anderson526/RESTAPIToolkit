<?php

namespace RestApiToolkit\Admin\Pages;

/**
 * Sección de donaciones: enlaces configurables (PayPal, Ko-fi, GitHub Sponsors,
 * Buy Me a Coffee) y otras formas de apoyar el proyecto.
 */
class DonationsPage
{
    public const OPTION = 'rat_donations';

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<style>
            .rm-donate-card {
                width: 100%;
                max-width: 100%;
                margin: 24px auto 0;
                padding: 32px 28px;
                border-radius: 18px;
                text-align: center;
                background: linear-gradient(180deg, #fffdf7 0%, #ffffff 100%);
                border: 1px solid rgba(233, 193, 98, 0.55);
                box-shadow: 0 12px 24px rgba(77, 59, 17, 0.08), 0 2px 8px rgba(77, 59, 17, 0.04);
                box-sizing: border-box;
            }
            .rm-donate-card h2 {
                margin: 12px 0 8px;
                font-size: 22px;
                line-height: 1.3;
                color: #1d2327;
            }
            .rm-donate-card p {
                margin: 0 0 18px;
                color: #50575e;
                font-size: 14px;
                line-height: 1.6;
            }
            .rm-coffee-emoji {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 64px;
                height: 64px;
                font-size: 38px;
                border-radius: 16px;
                background: rgba(255, 196, 57, 0.12);
                box-shadow: inset 0 0 0 1px rgba(255, 196, 57, 0.25);
                animation: rm-steam 2.4s ease-in-out infinite;
            }
            @keyframes rm-steam {
                0%, 100% { transform: translateY(0) rotate(0deg); }
                50% { transform: translateY(-4px) rotate(-4deg); }
            }
            .rm-donate-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                min-width: 220px;
                padding: 14px 28px;
                border: none;
                border-radius: 999px;
                background: linear-gradient(135deg, #ffd76a 0%, #f9b52a 100%);
                color: #3c2a00;
                font-size: 15px;
                font-weight: 800;
                text-decoration: none;
                letter-spacing: 0.01em;
                box-shadow: 0 10px 18px rgba(198, 136, 15, 0.28);
                transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
            }
            .rm-donate-button:hover,
            .rm-donate-button:focus-visible {
                transform: translateY(-2px) scale(1.02);
                box-shadow: 0 16px 24px rgba(198, 136, 15, 0.35);
                filter: brightness(1.02);
                color: #3c2a00;
            }
            .rm-donate-button:active {
                transform: translateY(0) scale(0.99);
            }
            .rm-donate-button-coffee {
                display: inline-block;
                font-size: 18px;
            }
            .rm-donate-link-note {
                margin-top: 16px;
                font-size: 12px;
                color: #6b7280;
            }
            @media (max-width: 480px) {
                .rm-donate-card {
                    padding: 24px 18px;
                }
                .rm-donate-button {
                    width: 100%;
                }
            }
        </style>';

        echo '<div class="wrap rat-wrap">';
        echo '<h1>' . esc_html__( 'REST API Toolkit — Donaciones', 'rest-api-toolkit' ) . '</h1>';
        echo '<div class="rm-card rm-donate-card">';
        echo '<div class="rm-coffee-emoji" aria-hidden="true">☕</div>';
        echo '<h2>' . esc_html__( '¿Te resulta útil REST API Toolkit?', 'rest-api-toolkit' ) . '</h2>';
        echo '<p>' . esc_html__( 'Tu apoyo ayuda a mantener y mejorar este plugin. Puedes hacer una donación directa desde PayPal.', 'rest-api-toolkit' ) . '</p>';

        echo '<a href="' . esc_url( 'https://paypal.me/AndersonChila?locale.x=en_US&country.x=CO' ) . '" class="rm-donate-button" target="_blank" rel="noopener noreferrer">';
        echo '<span class="rm-donate-button-coffee" aria-hidden="true">☕</span> ';
        echo esc_html__( 'Donar con PayPal', 'rest-api-toolkit' );
        echo '</a>';

        echo '<p class="rm-donate-link-note">' . esc_html__( 'Se abrirá PayPal en una nueva ventana.', 'rest-api-toolkit' ) . '</p>';
        echo '</div>';
        echo '</div>';
    }
}
