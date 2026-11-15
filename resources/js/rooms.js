/*
 * Rooms (docs/architecture/field-persistence.md): each page declares what kind of place it is on its
 * root element, and this copies that to <html>, where the tokens read it.
 *
 *   <div data-room="white">          a white room: the field fades out (Your ITPs, an ITP's page, the record, You)
 *   <div data-room="light">          a room made of light (Today, the player, Breathe 11, the Why, the making)
 *   <div data-room="light" data-bar="hidden">   a place you are *in*: the bar slides away
 *   <div ... data-scene="inside">    optional: a finer state the field may react to
 *
 * Runs on first paint and after every Livewire navigation. A page without data-room is a light room.
 */

const root = document.documentElement

export function applyRoom() {
    const page = document.querySelector('main.app-page > [data-room], main.app-page [data-room]')
    root.dataset.room = page?.dataset.room === 'white' ? 'white' : 'light'
    if (page?.dataset.bar === 'hidden') root.dataset.bar = 'hidden'
    else delete root.dataset.bar
    if (page?.dataset.scene) root.dataset.scene = page.dataset.scene
    else delete root.dataset.scene
}

applyRoom()
document.addEventListener('livewire:navigated', applyRoom)
