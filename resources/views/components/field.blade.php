{{--
    The field of light. Rendered once, inside @persist('field') in the layout, and drawn by
    resources/js/field.js. Nothing in here animates in CSS (platform.md §8: @persist restarts
    CSS animations on every navigation); the two canvases carry all the motion.
--}}
<div class="field" aria-hidden="true">
    <canvas class="field__canvas field__canvas--light"></canvas>
    <canvas class="field__canvas"></canvas>
    <div class="field__grain"></div>
</div>
