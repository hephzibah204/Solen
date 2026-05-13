//...

describe('stripInstructionBlocks', () => {
  it('should strip stray instruction blocks', () => {
    const text = '/* instruction block */ hello world';
    expect(stripInstructionBlocks(text)).toBe('hello world');
  });
});

//...