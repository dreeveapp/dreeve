import { autoUpdate, computePosition, flip, offset as offsetMiddleware, shift } from '@floating-ui/dom';

export default function anchor({ reference, floating, placement = 'bottom', offset = 0, skidding = 0 }) {
    return autoUpdate(reference, floating, () => {
        computePosition(reference, floating, {
            placement,
            strategy: 'fixed',
            middleware: [
                offsetMiddleware({ mainAxis: offset, crossAxis: skidding }),
                flip(),
                shift({ padding: 8 }),
            ],
        }).then(({ x, y }) => {
            Object.assign(floating.style, {
                left: `${x}px`,
                top: `${y}px`,
            });
        });
    });
}
