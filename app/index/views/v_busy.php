<?php
if (!ENV_SITE) {
	http_response_code(404); die;
}
if (empty($busyText)) {
    return;
}
?>

<style>
    .busy-container {
        position: fixed;
        top: 50%;
        left: 50%;
        z-index: 10000;
        transform: translate(-50%, -50%) rotate(-45deg);
        border: 10px solid red;
        padding: 25px 45px;
        background-color: rgba(255, 255, 255, 0.85);
        border-radius: 8px;
        box-shadow: 0 0 25px rgba(0, 0, 0, 0.5),
                    inset 0 0 10px rgba(255, 0, 0, 0.3);
        opacity: 0;
        animation: appearEffect 1.5s ease-out forwards,
                   pulseEffect 2.5s infinite ease-in-out 1.7s;
        filter: url(#gnawedEdge);
    }

    .busy-text {
        font-size: clamp(50px, 15vw, 200px);
        font-weight: bold;
        color: #333;
        text-align: center;
        text-transform: uppercase;
        text-shadow: 2px 2px 3px rgba(0, 0, 0, 0.3),
                     -1px -1px 0 #fff;
        white-space: nowrap;
        display: block;
    }

    @keyframes appearEffect {
        0% {
            opacity: 0;
            transform: translate(-50%, -50%) rotate(-45deg) scale(0.8);
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2),
                        inset 0 0 5px rgba(255, 0, 0, 0.1);
        }
        70% {
            opacity: 0.9;
            transform: translate(-50%, -50%) rotate(-45deg) scale(1.05);
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.6),
                        inset 0 0 12px rgba(255, 0, 0, 0.4);
        }
        100% {
            opacity: 1;
            transform: translate(-50%, -50%) rotate(-45deg) scale(1);
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.5),
                        inset 0 0 10px rgba(255, 0, 0, 0.3);
        }
    }

    /* Анимация пульсации */
    @keyframes pulseEffect {
        0% {
            transform: translate(-50%, -50%) rotate(-45deg) scale(1);
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.5),
                        inset 0 0 10px rgba(255, 0, 0, 0.3);
        }
        50% {
            /* Слегка увеличиваем */
            transform: translate(-50%, -50%) rotate(-45deg) scale(1.03);
             /* Усиливаем тень для эффекта */
            box-shadow: 0 0 35px rgba(0, 0, 0, 0.6),
                        inset 0 0 15px rgba(255, 0, 0, 0.4);
        }
        100% {
            transform: translate(-50%, -50%) rotate(-45deg) scale(1);
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.5),
                        inset 0 0 10px rgba(255, 0, 0, 0.3);
        }
    }
    .busy-container::before {
        animation: pulseShadow 2.5s infinite ease-in-out 1.7s;
    }
    @keyframes pulseShadow {
         0% {
             box-shadow: 0 0 25px rgba(0, 0, 0, 0.5), inset 0 0 10px rgba(255, 0, 0, 0.3);
         }
         50% {
            box-shadow: 0 0 35px rgba(0, 0, 0, 0.6), inset 0 0 15px rgba(255, 0, 0, 0.4);
         }
         100% {
             box-shadow: 0 0 25px rgba(0, 0, 0, 0.5), inset 0 0 10px rgba(255, 0, 0, 0.3);
         }
    }    
</style>

<svg style="position:absolute; height:0; width:0;">
    <defs>
        <filter id="gnawedEdge">
            <feTurbulence type="fractalNoise" baseFrequency="0.02 0.08" numOctaves="3" result="noise"/>
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="8" xChannelSelector="R" yChannelSelector="G"/>
        </filter>
    </defs>
</svg>

<div class="busy-container">
    <span class="busy-text"><?=$busyText?></span>
</div>
