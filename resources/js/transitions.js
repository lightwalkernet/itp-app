/*
 * Page transitions for Livewire full-page components using the View Transitions API (platform.md §8),
 * ported from the itpinterface spike.
 *
 * Links opt in with data-transition="forward" | "back" | "tab".
 * They deliberately do NOT use wire:navigate, so Livewire's own click
 * handling can't race with ours. We call Livewire.navigate() ourselves.
 *
 * Pages slide on the mobile build only (html.is-native). The web build navigates the same way,
 * with no animation.
 */

const root = document.documentElement
const stack = []            // Pages we navigated forward from ({ html, scrollY }). Survives because the JS context persists.
const SWIPE_VARS = ['--swipe-from', '--under-from', '--swipe-duration']
let busy = false
let internalBack = false

const currentPage = () => document.querySelector('main.app-page')

// Durations and easing live in transitions.css as custom properties on :root. Read them, don't copy them.
function cssTime(name) {
    const value = getComputedStyle(root).getPropertyValue(name).trim()
    return value.endsWith('ms') ? parseFloat(value) : parseFloat(value) * 1000
}

const pageEasing = () => getComputedStyle(root).getPropertyValue('--page-easing').trim()

const canAnimate = () =>
    root.classList.contains('is-native') &&
    typeof document.startViewTransition === 'function' &&
    !matchMedia('(prefers-reduced-motion: reduce)').matches

function waitForNavigated(timeout = 4000) {
    return new Promise(resolve => {
        const done = () => { clearTimeout(timer); resolve() }
        const timer = setTimeout(() => {
            document.removeEventListener('livewire:navigated', done)
            resolve()
        }, timeout)
        document.addEventListener('livewire:navigated', done, { once: true })
    })
}

function run(direction, navigate, afterSwap = () => {}) {
    if (busy) return
    busy = true

    const finish = () => {
        delete root.dataset.navDirection
        SWIPE_VARS.forEach(name => root.style.removeProperty(name))
        currentPage()?.style.removeProperty('view-transition-name')
        busy = false
    }

    if (!canAnimate()) {
        const done = waitForNavigated()
        navigate()
        done.then(afterSwap).then(finish)
        return
    }

    root.dataset.navDirection = direction
    const swipeVars = SWIPE_VARS.map(name => [name, root.style.getPropertyValue(name)])
    currentPage()?.style.setProperty('view-transition-name', 'page-out')

    const transition = document.startViewTransition(async () => {
        const done = waitForNavigated()
        navigate()
        await done
        // Livewire's swap copies the incoming <html> attributes onto the live one (and history.back()
        // restores the snapshotted ones), wiping our direction. Put it back before the animation starts.
        root.dataset.navDirection = direction
        swipeVars.forEach(([name, value]) => value && root.style.setProperty(name, value))
        // Also replaces the "page-out" name a history.back() restore brings back from Livewire's snapshot.
        currentPage()?.style.setProperty('view-transition-name', 'page-in')
        afterSwap()
    })

    transition.finished.finally(finish)
}

export function forward(url) {
    const main = currentPage()
    stack.push({ html: main ? main.innerHTML : '', scrollY })
    run('forward', () => Livewire.navigate(url))
}

export function back(fallbackUrl) {
    if (stack.length) {
        const previous = stack.pop()
        internalBack = true
        // Real history.back() keeps the browser history and Android back button in sync.
        // Put the scroll back where it was, so the page lands where the swipe underlay showed it.
        run('back', () => history.back(), () => scrollTo({ top: previous.scrollY, behavior: 'instant' }))
    } else if (fallbackUrl) {
        run('back', () => Livewire.navigate(fallbackUrl))
    }
}

function tab(link) {
    const tabs = [...document.querySelectorAll('a[data-transition="tab"]')]
    const to = tabs.indexOf(link)
    const from = tabs.findIndex(t => t.getAttribute('aria-current') === 'page')
    if (to === from && !stack.length) return

    stack.length = 0
    run(to < from ? 'back' : 'forward', () => Livewire.navigate(link.href))
}

// The tab bar is @persist-ed, so Blade can't update its active state. Do it here.
function syncTabs() {
    const tabs = [...document.querySelectorAll('a[data-transition="tab"]')]
    // Longest matching prefix wins, so /account beats /
    const active = tabs
        .filter(t => location.pathname.startsWith(t.dataset.tabMatch))
        .sort((a, b) => b.dataset.tabMatch.length - a.dataset.tabMatch.length)[0]
    tabs.forEach(t => t === active
        ? t.setAttribute('aria-current', 'page')
        : t.removeAttribute('aria-current'))
}

document.addEventListener('livewire:navigated', syncTabs)

// Hardware / gesture back that we didn't start: keep our stack in step.
window.addEventListener('popstate', () => {
    if (internalBack) internalBack = false
    else stack.pop()
})

document.addEventListener('click', e => {
    const link = e.target.closest('a[data-transition]')
    if (!link || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) return
    e.preventDefault()

    const type = link.dataset.transition
    if (type === 'back') back(link.href)
    else if (type === 'tab') tab(link)
    else forward(link.href)
})

/* ---------- Interactive edge swipe to go back ---------- */

const EDGE = 28          // px from the left edge where a swipe can start
const UNDER_SHIFT = -100 // % the previous page sits left of its resting place, matching app-in-left
const RELEASE_MIN = 0.4  // shortest swipe release, as a share of --page-duration
let drag = null

// After a swipe the page only has the rest of the way to go, so it gets that share of a tapped transition.
const releaseDuration = remaining => Math.round(cssTime('--page-duration') * Math.max(RELEASE_MIN, remaining))

// A static copy of the previous page, shown behind the one being swiped away.
function showUnderlay(previous) {
    const content = document.createElement('div')
    content.innerHTML = previous.html
    // Strip anything Livewire or Alpine would try to boot, or it becomes a second live component.
    content.querySelectorAll('script').forEach(script => script.remove())
    content.querySelectorAll('*').forEach(el => [...el.attributes]
        .filter(attr => /^(wire:|x-|@|:)/.test(attr.name))
        .forEach(attr => el.removeAttribute(attr.name)))
    content.style.transform = `translateY(${-previous.scrollY}px)`

    const underlay = document.createElement('div')
    underlay.className = 'app-underlay'
    underlay.inert = true
    underlay.setAttribute('aria-hidden', 'true')
    underlay.append(content)
    document.body.append(underlay)
    return underlay
}

function moveUnderlay(underlay, progress) {
    underlay.style.transform = `translateX(${UNDER_SHIFT * (1 - progress)}%)`
}

function snapBack(main, duration) {
    main.style.transition = `transform ${duration}ms ${pageEasing()}`
    main.style.transform = ''
    main.addEventListener('transitionend', () => { main.style.transition = '' }, { once: true })
}

window.addEventListener('touchstart', e => {
    // Device only: a mobile browser has its own back swipe, and off device pages don't slide.
    if (busy || e.touches.length !== 1 || !root.classList.contains('is-native')) return
    const t = e.touches[0]
    if (t.clientX > EDGE) return

    const page = document.querySelector('[data-swipe-back]')
    const main = currentPage()
    if (!page || !main) return
    if (!stack.length && !page.dataset.backUrl) return

    drag = {
        main, page,
        x0: t.clientX, y0: t.clientY,
        x: t.clientX, lastX: t.clientX, lastT: performance.now(),
        velocity: 0, locked: false,
    }
}, { passive: true })

window.addEventListener('touchmove', e => {
    if (!drag) return
    const t = e.touches[0]
    const dx = t.clientX - drag.x0
    const dy = t.clientY - drag.y0

    if (!drag.locked) {
        if (Math.abs(dy) > 8 && Math.abs(dy) > Math.abs(dx)) { drag = null; return } // vertical scroll
        if (dx < 8) return
        drag.locked = true
        drag.main.style.transition = 'none'
        root.classList.add('is-swiping')
        if (stack.length) drag.underlay = showUnderlay(stack[stack.length - 1])
    }

    e.preventDefault()

    const now = performance.now()
    drag.velocity = (t.clientX - drag.lastX) / Math.max(1, now - drag.lastT)
    drag.lastX = t.clientX
    drag.lastT = now
    drag.x = t.clientX

    drag.main.style.transform = `translateX(${Math.max(0, dx)}px)`
    if (drag.underlay) moveUnderlay(drag.underlay, Math.max(0, dx) / innerWidth)
}, { passive: false })

function endDrag(cancelled) {
    if (!drag) return
    const d = drag
    drag = null
    if (!d.locked) return

    root.classList.remove('is-swiping')
    const dx = Math.max(0, d.x - d.x0)
    const commit = !cancelled && (dx > innerWidth * 0.35 || (d.velocity > 0.5 && dx > 40))

    const progress = dx / innerWidth

    if (!commit) {
        const duration = releaseDuration(progress)
        snapBack(d.main, duration)
        if (d.underlay) {
            d.underlay.style.transition = `transform ${duration}ms ${pageEasing()}`
            moveUnderlay(d.underlay, 0)
            setTimeout(() => d.underlay.remove(), duration + 20)
        }
        return
    }

    // Hand off to the view transition: both pages start where the finger left them.
    // The page and underlay stay where they are until Livewire's swap replaces them,
    // so no frame shows the page snapped back before the old snapshot is taken.
    root.style.setProperty('--swipe-from', `${dx}px`)
    root.style.setProperty('--under-from', `${UNDER_SHIFT * (1 - progress)}%`)
    root.style.setProperty('--swipe-duration', `${releaseDuration(1 - progress)}ms`)
    back(d.page.dataset.backUrl)
}

window.addEventListener('touchend', () => endDrag(false))
window.addEventListener('touchcancel', () => endDrag(true))

syncTabs()
window.pageTransitions = { forward, back }
